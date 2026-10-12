/**
 * Assembles the PBL report (.docx). Formatting follows the university template:
 * Times New Roman, body 12 pt, chapter headings 14 pt bold ALL CAPS, line
 * spacing 1.5, justified, margins left 1.25" and 1" elsewhere, page border kept.
 */
const fs = require('fs');
const path = require('path');
const {
  Document, Packer, Paragraph, TextRun, AlignmentType, BorderStyle,
  Footer, PageNumber, LevelFormat,
} = require('docx');

/* ------------------------------------------------------------------ content */
const body = require('./content.js');

const doc = new Document({
  creator: 'Woxsen University — School of Technology',
  title: 'Fetch — Online Food Ordering System: PBL Report',
  styles: {
    default: {
      document: {
        run: { font: 'Times New Roman', size: 24 },
        paragraph: { spacing: { line: 360, after: 120 }, alignment: AlignmentType.JUSTIFIED },
      },
      heading1: { run: { font: 'Times New Roman', size: 28, bold: true, color: '000000' } },
      heading2: { run: { font: 'Times New Roman', size: 24, bold: true, color: '000000' } },
    },
  },
  numbering: {
    config: [{
      reference: 'bullets',
      levels: [{ level: 0, format: LevelFormat.BULLET, text: '•', alignment: AlignmentType.LEFT,
        style: { paragraph: { indent: { left: 460, hanging: 260 } } } }],
    }],
  },
  sections: [{
    properties: {
      page: {
        size: { width: 11906, height: 16838 },                        // A4, as in the template
        margin: { top: 1440, right: 1440, bottom: 1440, left: 1800 }, // left 1.25", others 1"
        borders: {
          pageBorderTop: { style: BorderStyle.SINGLE, size: 4, space: 24, color: 'auto' },
          pageBorderLeft: { style: BorderStyle.SINGLE, size: 4, space: 24, color: 'auto' },
          pageBorderBottom: { style: BorderStyle.SINGLE, size: 4, space: 24, color: 'auto' },
          pageBorderRight: { style: BorderStyle.SINGLE, size: 4, space: 24, color: 'auto' },
        },
      },
    },
    footers: {
      default: new Footer({
        children: [new Paragraph({
          alignment: AlignmentType.CENTER,
          spacing: { line: 240, after: 0 },
          children: [new TextRun({ children: [PageNumber.CURRENT], size: 20 })],
        })],
      }),
    },
    children: body,
  }],
});

Packer.toBuffer(doc).then((buf) => {
  const out = path.join(__dirname, 'PBL Report — Fetch Online Food Ordering System.docx');
  fs.writeFileSync(out, buf);
  console.log('written:', out, (buf.length / 1024).toFixed(0) + ' KB');
});
