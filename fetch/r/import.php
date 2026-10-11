<?php
/**
 * Bulk menu import.
 *
 * The uploaded file is validated against sql/menu.xsd first — if the schema
 * rejects it, nothing is written. Valid files are then walked with the DOM
 * parser and inserted inside one transaction.
 */
require_once __DIR__ . '/../config.php';
$user = require_role('restaurant');
$r    = my_restaurant();

$errors = [];
$added  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (empty($_FILES['xml']['tmp_name'])) {
        $errors[] = 'Choose an XML file first.';
    } else {
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();

        if (!$doc->load($_FILES['xml']['tmp_name'])) {
            $errors[] = 'That file is not well-formed XML.';
        } elseif (!$doc->schemaValidate(ROOT_PATH . '/sql/menu.xsd')) {
            foreach (libxml_get_errors() as $err) {
                $errors[] = 'Line ' . $err->line . ': ' . trim($err->message);
            }
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $count = 0;
                foreach ($doc->getElementsByTagName('section') as $section) {
                    $sectionName = $section->getAttribute('name');
                    $cat = q1('SELECT id FROM categories WHERE restaurant_id = ? AND name = ?', [$r['id'], $sectionName]);
                    $catId = $cat['id'] ?? insert(
                        'INSERT INTO categories (restaurant_id, name, sort_order) VALUES (?,?,?)',
                        [$r['id'], $sectionName, (int) q1('SELECT COALESCE(MAX(sort_order),0)+1 n FROM categories WHERE restaurant_id = ?', [$r['id']])['n']]
                    );

                    foreach ($section->getElementsByTagName('dish') as $dish) {
                        $get = fn(string $tag) => $dish->getElementsByTagName($tag)->item(0)?->textContent;
                        insert(
                            'INSERT INTO menu_items (restaurant_id, category_id, name, description, price, is_veg, prep_minutes)
                             VALUES (?,?,?,?,?,?,?)',
                            [$r['id'], $catId, trim((string) $get('name')), trim((string) $get('description')),
                             (float) $get('price'), $get('veg') === 'true' ? 1 : 0, (int) $get('prepMinutes')]
                        );
                        $count++;
                    }
                }
                $pdo->commit();
                $added = $count;
                flash($count . ' dishes imported.');
            } catch (Throwable $e) {
                $pdo->rollBack();
                $errors[] = 'Import failed: ' . $e->getMessage();
            }
        }
        libxml_clear_errors();
    }
}

page_head('Import menu — ' . $r['name']);
topbar();
?>
<div class="shell">
  <?php dash_side('restaurant', 'menu'); ?>
  <main class="main">
    <div class="page-head"><div><div class="mono-k">Menu · bulk import</div><h1 class="h1" style="margin-top:6px">Import from <span class="serif">XML.</span></h1></div></div>

    <div class="two-col">
      <div class="card">
        <?php if ($errors): ?>
          <div class="alert alert--err"><b>Nothing was imported.</b><br><?= implode('<br>', array_map('e', $errors)) ?></div>
        <?php elseif ($added !== null): ?>
          <div class="alert alert--ok"><b><?= $added ?> dishes added.</b> They are live on your menu now.</div>
        <?php endif; ?>

        <p class="muted">Upload a menu file and we check it against our schema before touching the database.
          Anything that doesn't match is rejected with the line number, so a bad file can never half-import.</p>

        <form method="post" enctype="multipart/form-data" class="mt">
          <?= csrf_field() ?>
          <label class="field"><span>Menu file (.xml)</span><input class="input" type="file" name="xml" accept=".xml,text/xml" required></label>
          <button class="btn btn--primary">Validate &amp; import</button>
          <a class="btn btn--ghost" href="<?= base_url('sql/menu-sample.xml') ?>" download>Download a sample file</a>
        </form>
      </div>

      <aside class="sticky">
        <div class="card">
          <div class="mono-k mb">Expected shape</div>
<pre class="mono xs" style="overflow-x:auto;color:var(--ink-2);line-height:1.7">&lt;menu restaurant="<?= e($r['name']) ?>"&gt;
  &lt;section name="Desserts"&gt;
    &lt;dish&gt;
      &lt;name&gt;Gulab Jamun&lt;/name&gt;
      &lt;description&gt;Warm&lt;/description&gt;
      &lt;price&gt;80&lt;/price&gt;
      &lt;prepMinutes&gt;4&lt;/prepMinutes&gt;
      &lt;veg&gt;true&lt;/veg&gt;
    &lt;/dish&gt;
  &lt;/section&gt;
&lt;/menu&gt;</pre>
          <p class="xs dim mt">Validated with <code>DOMDocument::schemaValidate()</code> against
            <code>sql/menu.xsd</code>, then parsed with the DOM API.</p>
        </div>
      </aside>
    </div>
  </main>
</div>
<?php page_foot(); ?>
