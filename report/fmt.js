/**
 * Builds the PBL report (.docx) for the Fetch online food ordering system.
 *
 * Formatting follows the university template exactly:
 *   Times New Roman · body 12 pt · chapter headings 14 pt bold ALL CAPS
 *   line spacing 1.5 · justified · margins left 1.25", others 1"
 *   page border preserved from the template
 *
 * Blanks that the student fills in by hand are left as underscores.
 */
const fs = require('fs');
const path = require('path');
const {
  Document, Packer, Paragraph, TextRun, ImageRun, Table, TableRow, TableCell,
  AlignmentType, HeadingLevel, PageBreak, WidthType, BorderStyle, ShadingType,
  TableOfContents, Footer, PageNumber, VerticalAlign, LevelFormat, convertInchesToTwip,
} = require('docx');

const FIG = path.join(__dirname, 'figures');
const CONTENT_W = 8666;           // twips available between the margins
const IMG_W = 560;                // px, fits the text column

/* ------------------------------------------------------------------ helpers */
const P = (text, opts = {}) => new Paragraph({
  alignment: opts.align || AlignmentType.JUSTIFIED,
  spacing: { line: 360, after: opts.after === undefined ? 120 : opts.after, before: opts.before || 0 },
  indent: opts.indent,
  children: [new TextRun({ text, bold: opts.bold, italics: opts.italics, size: opts.size || 24, allCaps: opts.caps })],
});

/** Rich paragraph: pass an array of [text, {bold/italics/mono}] pairs. */
const RP = (runs, opts = {}) => new Paragraph({
  alignment: opts.align || AlignmentType.JUSTIFIED,
  spacing: { line: 360, after: opts.after === undefined ? 120 : opts.after },
  children: runs.map(([t, o = {}]) => new TextRun({
    text: t, bold: o.b, italics: o.i, size: o.size || 24,
    font: o.mono ? 'Consolas' : undefined,
  })),
});

const H1 = (text) => new Paragraph({
  heading: HeadingLevel.HEADING_1,
  alignment: AlignmentType.LEFT,
  spacing: { line: 360, before: 240, after: 240 },
  children: [new TextRun({ text: text.toUpperCase(), bold: true, size: 28, allCaps: true })],
});

const H2 = (text) => new Paragraph({
  heading: HeadingLevel.HEADING_2,
  alignment: AlignmentType.LEFT,
  spacing: { line: 360, before: 200, after: 120 },
  children: [new TextRun({ text, bold: true, size: 24 })],
});

const BULLET = (text, opts = {}) => new Paragraph({
  bullet: { level: 0 },
  alignment: AlignmentType.JUSTIFIED,
  spacing: { line: 360, after: 80 },
  children: [new TextRun({ text, size: 24, bold: opts.bold })],
});

/** Bullet whose opening phrase is bold: BOLDBULLET('Role-based access', ' — rest of it.') */
const BBULLET = (lead, rest) => new Paragraph({
  bullet: { level: 0 },
  alignment: AlignmentType.JUSTIFIED,
  spacing: { line: 360, after: 80 },
  children: [
    new TextRun({ text: lead, bold: true, size: 24 }),
    new TextRun({ text: rest, size: 24 }),
  ],
});

const CODE = (lines) => new Paragraph({
  alignment: AlignmentType.LEFT,
  spacing: { line: 260, after: 120, before: 60 },
  indent: { left: 360 },
  children: lines.flatMap((l, i) => [
    new TextRun({ text: l, font: 'Consolas', size: 20, break: i === 0 ? 0 : 1 }),
  ]),
});

const CAPTION = (text) => new Paragraph({
  alignment: AlignmentType.CENTER,
  spacing: { line: 240, after: 240, before: 80 },
  children: [new TextRun({ text, size: 20, italics: true })],
});

const PAGEBREAK = () => new Paragraph({ children: [new PageBreak()] });

/** Image sized to the text column, keeping the source aspect ratio. */
function IMAGE(file, widthPx = IMG_W) {
  const buf = fs.readFileSync(path.join(FIG, file));
  const isPng = file.endsWith('.png');
  // read intrinsic size so the aspect ratio is right
  let w, h;
  if (isPng) {
    w = buf.readUInt32BE(16); h = buf.readUInt32BE(20);
  } else {
    // minimal JPEG SOF scan
    let i = 2;
    while (i < buf.length) {
      if (buf[i] !== 0xff) { i++; continue; }
      const marker = buf[i + 1];
      if (marker >= 0xc0 && marker <= 0xcf && marker !== 0xc4 && marker !== 0xc8 && marker !== 0xcc) {
        h = buf.readUInt16BE(i + 5); w = buf.readUInt16BE(i + 7); break;
      }
      i += 2 + buf.readUInt16BE(i + 2);
    }
  }
  const height = Math.round(widthPx * (h / w));
  return new Paragraph({
    alignment: AlignmentType.CENTER,
    spacing: { before: 160, after: 40 },
    children: [new ImageRun({ type: isPng ? 'png' : 'jpg', data: buf, transformation: { width: widthPx, height } })],
  });
}

/** Table with a header row; widths are fractions of the text column. */
function TABLE(headers, rows, fractions) {
  const widths = fractions.map((f) => Math.round(CONTENT_W * f));
  const cell = (text, { bold = false, header = false } = {}, i = 0) => new TableCell({
    width: { size: widths[i], type: WidthType.DXA },
    shading: header ? { type: ShadingType.CLEAR, fill: 'EFEAE3' } : undefined,
    margins: { top: 60, bottom: 60, left: 100, right: 100 },
    verticalAlign: VerticalAlign.CENTER,
    children: String(text).split('\n').map((line) => new Paragraph({
      alignment: AlignmentType.LEFT,
      spacing: { line: 240, after: 0 },
      children: [new TextRun({ text: line, bold: bold || header, size: 20 })],
    })),
  });
  return new Table({
    columnWidths: widths,
    width: { size: CONTENT_W, type: WidthType.DXA },
    borders: {
      top: { style: BorderStyle.SINGLE, size: 2, color: '555555' },
      bottom: { style: BorderStyle.SINGLE, size: 2, color: '555555' },
      left: { style: BorderStyle.SINGLE, size: 2, color: '555555' },
      right: { style: BorderStyle.SINGLE, size: 2, color: '555555' },
      insideHorizontal: { style: BorderStyle.SINGLE, size: 1, color: '999999' },
      insideVertical: { style: BorderStyle.SINGLE, size: 1, color: '999999' },
    },
    rows: [
      new TableRow({
        tableHeader: true,
        children: headers.map((h, i) => cell(h, { header: true }, i)),
      }),
      ...rows.map((r) => new TableRow({ children: r.map((c, i) => cell(c, {}, i)) })),
    ],
  });
}

module.exports = { P, RP, H1, H2, BULLET, BBULLET, CODE, CAPTION, PAGEBREAK, IMAGE, TABLE, CONTENT_W, IMG_W };
