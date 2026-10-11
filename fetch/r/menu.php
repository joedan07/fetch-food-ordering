<?php
/** Menu management: add, edit, delete, photo upload, sold-out switch. */
require_once __DIR__ . '/../config.php';
$user = require_role('restaurant');
$r    = my_restaurant();
$rid  = (int) $r['id'];

$editing = null;
if (isset($_GET['edit'])) {
    $editing = q1('SELECT * FROM menu_items WHERE id = ? AND restaurant_id = ?', [(int) $_GET['edit'], $rid]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $do = $_POST['do'] ?? '';

    if ($do === 'delete') {
        exec_sql('DELETE FROM menu_items WHERE id = ? AND restaurant_id = ?', [(int) $_POST['id'], $rid]);
        flash('Dish removed.');
        redirect('r/menu.php');
    }

    if ($do === 'category') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            insert('INSERT INTO categories (restaurant_id, name, sort_order) VALUES (?,?,?)',
                [$rid, $name, (int) (q1('SELECT COALESCE(MAX(sort_order),0)+1 n FROM categories WHERE restaurant_id = ?', [$rid])['n'])]);
            flash('Section added.');
        }
        redirect('r/menu.php');
    }

    // add / edit a dish
    $name  = trim($_POST['name'] ?? '');
    $price = (float) ($_POST['price'] ?? 0);
    $prep  = max(1, (int) ($_POST['prep_minutes'] ?? 10));
    $cat   = (int) ($_POST['category_id'] ?? 0) ?: null;
    $desc  = trim($_POST['description'] ?? '');
    $veg   = isset($_POST['is_veg']) ? 1 : 0;
    $av    = isset($_POST['is_available']) ? 1 : 0;
    $id    = (int) ($_POST['id'] ?? 0);

    if ($name === '' || $price <= 0) {
        flash('A dish needs a name and a price.', 'err');
    } else {
        $img = save_upload('image');
        if ($id) {
            exec_sql(
                'UPDATE menu_items SET name=?, description=?, price=?, prep_minutes=?, category_id=?, is_veg=?, is_available=?'
                . ($img ? ', image=?' : '') . ' WHERE id=? AND restaurant_id=?',
                $img ? [$name, $desc, $price, $prep, $cat, $veg, $av, $img, $id, $rid]
                     : [$name, $desc, $price, $prep, $cat, $veg, $av, $id, $rid]
            );
            flash('Dish updated.');
        } else {
            insert(
                'INSERT INTO menu_items (restaurant_id, category_id, name, description, price, is_veg, prep_minutes, is_available, image)
                 VALUES (?,?,?,?,?,?,?,?,?)',
                [$rid, $cat, $name, $desc, $price, $veg, $prep, $av, $img]
            );
            flash('Dish added to the menu.');
        }
        redirect('r/menu.php');
    }
}

$cats  = q('SELECT * FROM categories WHERE restaurant_id = ? ORDER BY sort_order, id', [$rid]);
$items = q('SELECT i.*, c.name AS category FROM menu_items i LEFT JOIN categories c ON c.id = i.category_id
            WHERE i.restaurant_id = ? ORDER BY c.sort_order, i.name', [$rid]);

page_head('Menu — ' . $r['name']);
topbar();
?>
<div class="shell">
  <?php dash_side('restaurant', 'menu'); ?>
  <main class="main">
    <div class="page-head">
      <div>
        <div class="mono-k"><?= count($items) ?> dishes · <?= count($cats) ?> sections</div>
        <h1 class="h1" style="margin-top:6px">The <span class="serif">menu.</span></h1>
      </div>
      <a class="btn btn--ghost" href="<?= base_url('r/import.php') ?>">Import from XML</a>
    </div>

    <div class="two-col">
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Dish</th><th>Section</th><th>Price</th><th>Prep</th><th>Available</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($items as $i): ?>
            <tr>
              <td>
                <div class="row gap">
                  <span class="veg-dot <?= $i['is_veg'] ? '' : 'nv' ?>"></span>
                  <div><b><?= e($i['name']) ?></b><div class="xs dim"><?= e($i['description']) ?></div></div>
                </div>
              </td>
              <td class="sm dim"><?= e($i['category'] ?? '—') ?></td>
              <td class="num"><?= money((float) $i['price']) ?></td>
              <td class="num"><?= (int) $i['prep_minutes'] ?> min</td>
              <td><button class="switch <?= $i['is_available'] ? 'on' : '' ?>" onclick="toggleItem(<?= $i['id'] ?>, this)"><span></span></button></td>
              <td class="right">
                <div class="row gap" style="justify-content:flex-end">
                  <a class="btn btn--ghost btn--sm" href="?edit=<?= $i['id'] ?>">Edit</a>
                  <form method="post" data-confirm="Remove <?= e($i['name']) ?> from the menu?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= $i['id'] ?>">
                    <button class="btn btn--danger btn--sm">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <aside class="sticky">
        <form class="card" method="post" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
          <h3 class="h3 mb"><?= $editing ? 'Edit dish' : 'Add a dish' ?></h3>

          <label class="field"><span>Name</span><input class="input" name="name" required value="<?= e($editing['name'] ?? '') ?>"></label>
          <label class="field"><span>Description</span><input class="input" name="description" value="<?= e($editing['description'] ?? '') ?>"></label>
          <div class="row gap">
            <label class="field grow"><span>Price ₹</span><input class="input" type="number" name="price" min="1" step="1" required value="<?= e((string) ($editing['price'] ?? '')) ?>"></label>
            <label class="field grow"><span>Prep minutes</span><input class="input" type="number" name="prep_minutes" min="1" max="90" required value="<?= (int) ($editing['prep_minutes'] ?? 12) ?>"></label>
          </div>
          <p class="xs dim mb">Prep minutes feed the delivery estimate customers see — keep them honest.</p>

          <label class="field"><span>Section</span>
            <select class="input" name="category_id">
              <option value="">No section</option>
              <?php foreach ($cats as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ($editing['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="field"><span>Photo (optional)</span><input class="input" type="file" name="image" accept="image/*"></label>
          <div class="row gap mb">
            <label class="row gap sm"><input type="checkbox" name="is_veg" <?= ($editing['is_veg'] ?? 1) ? 'checked' : '' ?>> Vegetarian</label>
            <label class="row gap sm"><input type="checkbox" name="is_available" <?= ($editing['is_available'] ?? 1) ? 'checked' : '' ?>> Available</label>
          </div>
          <button class="btn btn--primary btn--block"><?= $editing ? 'Save changes' : 'Add to menu' ?></button>
          <?php if ($editing): ?><a class="btn btn--ghost btn--block mt" href="<?= base_url('r/menu.php') ?>">Cancel</a><?php endif; ?>
        </form>

        <form class="card mt" method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="do" value="category">
          <h3 class="h3 mb">New section</h3>
          <label class="field"><span>Name</span><input class="input" name="name" placeholder="Desserts" required></label>
          <button class="btn btn--ghost btn--block">Add section</button>
        </form>
      </aside>
    </div>
  </main>
</div>

<script>
async function toggleItem(id, el) {
  const on = !el.classList.contains('on');
  try {
    await api('/api/restaurant.php', { action: 'availability', item_id: id, available: on ? 1 : 0 });
    el.classList.toggle('on', on);
    toast(on ? 'Back on the menu' : 'Marked sold out');
  } catch (e) { toast(e.message, 'err'); }
}
</script>
<?php page_foot(); ?>
