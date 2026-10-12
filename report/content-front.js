/**
 * Preliminary pages — title, declaration, certificate, team, acknowledgement,
 * abstract, table of contents, list of figures, list of tables.
 *
 * The wording of the title, declaration and certificate pages is reproduced
 * from the university template; every blank is left for the student to fill in.
 */
const { Paragraph, TextRun, AlignmentType, HeadingLevel, TableOfContents } = require('docx');
const { P, H1, PAGEBREAK, TABLE } = require('./fmt');

const C = (text, opts = {}) => P(text, { align: AlignmentType.CENTER, ...opts });
const GAP = (n = 1) => Array.from({ length: n }, () => P('', { after: 0 }));

module.exports = [
  /* ----------------------------------------------------------- title page */
  ...GAP(1),
  C('Woxsen University', { bold: true, size: 32 }),
  C('School of Technology', { bold: true, size: 28 }),
  ...GAP(1),
  C('B.Tech – Computer Science and Engineering', { bold: true }),
  ...GAP(1),
  C('PROJECT BASED LEARNING REPORT', { bold: true, size: 28 }),
  ...GAP(1),
  C('Title of the Project', { bold: true }),
  C('(Fetch — Design and Implementation of a Multi-Restaurant Online Food Ordering System with a Live Shared Cart and a Kitchen-Load-Based Delivery Time Estimator)', { bold: true }),
  ...GAP(1),
  C('Course Code: __________'),
  C('Course Name: __________'),
  C('Semester: __________'),
  C('Academic Year: __________'),
  ...GAP(1),
  C('Submitted by:', { bold: true }),
  C('Name: ____________________'),
  C('Register Number: __________'),
  C('Section: __________'),
  ...GAP(1),
  C('Under the Guidance of:', { bold: true }),
  C('Faculty Name: ____________________'),
  C('Designation: ____________________'),
  C('Department of Computer Science and Engineering'),
  ...GAP(1),
  C('Month, Year'),
  PAGEBREAK(),

  /* ---------------------------------------------------------- declaration */
  C('DECLARATION', { bold: true, size: 28 }),
  ...GAP(1),
  P('I hereby declare that the Project Title', { align: AlignmentType.LEFT }),
  P('"______________________________________________"', { align: AlignmentType.LEFT }),
  P('submitted by me is an original work carried out under the guidance of the faculty mentioned above.'),
  P('I confirm that this work has not been submitted in part or full for the award of any degree, diploma, or other academic qualification in this or any other institution.'),
  P('All sources of information used in this case study have been properly acknowledged and cited as per university referencing standards.'),
  P('I further declare that the plagiarism percentage is within the permissible limit prescribed by the institution.'),
  ...GAP(2),
  P('Place: ____________                                        Date: ____________', { align: AlignmentType.LEFT }),
  ...GAP(2),
  P('Signature of the Candidate'),
  ...GAP(1),
  P('Name: ____________________                        Register Number: ____________', { align: AlignmentType.LEFT }),
  PAGEBREAK(),

  /* ---------------------------------------------------------- certificate */
  C('CERTIFICATE', { bold: true, size: 28 }),
  ...GAP(1),
  P('This is to certify that the Project titled', { align: AlignmentType.LEFT }),
  P('"_______________________________________________"', { align: AlignmentType.LEFT }),
  P('is a bonafide work carried out by Mr./Ms. ____________________________ (Register No: ___________), B.Tech – Computer Science and Engineering, during the academic year ____________, in partial fulfilment of the requirements of the Project Based Learning component of the course.', { align: AlignmentType.LEFT }),
  ...GAP(3),
  P('Guide Signature'),
  P('(Name of the Guide)'),
  P('Designation'),
  ...GAP(2),
  P('Head of the Department                                        External Examiner', { align: AlignmentType.LEFT }),
  PAGEBREAK(),

  /* --------------------------------------------------------- team members */
  C('TEAM MEMBERS', { bold: true, size: 28 }),
  ...GAP(1),
  P('The following students contributed to this Project Based Learning submission:'),
  ...GAP(1),
  P('Name – Roll Number: ________________________________', { align: AlignmentType.LEFT }),
  P('Name – Roll Number: ________________________________', { align: AlignmentType.LEFT }),
  P('Name – Roll Number: ________________________________', { align: AlignmentType.LEFT }),
  P('Name – Roll Number: ________________________________', { align: AlignmentType.LEFT }),
  ...GAP(1),
  P('Faculty Guide: ________________________________', { align: AlignmentType.LEFT }),
  PAGEBREAK(),

  /* ------------------------------------------------------ acknowledgement */
  C('ACKNOWLEDGEMENT', { bold: true, size: 28 }),
  ...GAP(1),
  P('We would like to express our sincere gratitude to our faculty guide, ____________________, Department of Computer Science and Engineering, for the guidance, patience and constructive criticism offered throughout this Project Based Learning exercise. The periodic reviews shaped the direction of the work far more than any single suggestion did, and the insistence on measurable outcomes rather than feature lists is the reason this report contains numbers at all.'),
  P('We are grateful to the Head of the Department and the faculty of the School of Technology, Woxsen University, for providing the laboratory facilities and the Web Technologies curriculum on which this project is built. Every component used here — HTML5, CSS3, JavaScript, XML with schema validation, PHP and MySQL — was introduced in the course laboratory sessions, and the project is in effect an attempt to assemble those individual exercises into one working product.'),
  P('We also thank our classmates, who tested the shared-cart feature on their own phones and reported the problems that two developers testing on one laptop could never have found, and our families for their encouragement during the long debugging sessions that followed.'),
  ...GAP(2),
  P('____________________', { align: AlignmentType.RIGHT }),
  P('(Signature of the Students)', { align: AlignmentType.RIGHT }),
  PAGEBREAK(),

  /* ---------------------------------------------------------- abstract */
  H1('Abstract'),
  P('Ordering food from small restaurants and campus canteens is still largely a telephone and counter-queue activity. Orders are taken by voice with no written record, printed menus fall out of date, the customer has no visibility of the order once it is placed, and the kitchen tracks everything on paper. Where delivery platforms do exist, two shortcomings remain conspicuous: ordering together with friends is clumsy, and the delivery time shown to the customer is a flat estimate that bears little relation to how busy the kitchen actually is.'),
  P('This project presents Fetch, a multi-restaurant online food ordering system developed entirely with the technologies covered in the Web Technologies course — HTML5, CSS3 and JavaScript on the client, PHP 8 on the server, MySQL for persistence, and XML validated against an XSD schema for bulk menu import. The system supports four roles, each with its own interface: customers browse and order, restaurants manage menus and move tickets through the kitchen, riders claim and complete deliveries, and an administrator approves restaurants and monitors the platform.'),
  P('Two features distinguish the system from existing applications. The first is a live group cart: one cart is shared through a six-character code, several people add items from their own devices simultaneously, every line records who added it, and the bill is split automatically. The second is an honest delivery estimate computed from the kitchen\'s real queue — the remaining cooking work divided by how many orders the kitchen can prepare at once, plus the cooking time of the basket and the travel time — which is shown before the customer commits, stored with the order as a promise, and measured against the actual delivery time afterwards.'),
  P('The system was deployed publicly and exercised with a seeded dataset of 28 orders across five restaurants. 83% of delivered orders arrived within the promised time, with a mean absolute error of 5.7 minutes between the promised and actual durations, and warm server responses averaged under one second. The results indicate that a transparent, queue-derived estimate is both implementable with ordinary server-side technologies and accurate enough to be useful.'),
  PAGEBREAK(),

  /* -------------------------------------------------------------- contents */
  H1('Table of Contents'),
  P('(Right-click the field below in Microsoft Word and choose "Update Field" to generate page numbers.)', { italics: true, size: 20 }),
  new TableOfContents('Table of Contents', { hyperlink: true, headingStyleRange: '1-2' }),
  PAGEBREAK(),

  /* ------------------------------------------------------- list of figures */
  H1('List of Figures'),
  TABLE(
    ['Figure', 'Caption'],
    [
      ['5.1', 'Three-tier architecture of the Fetch system'],
      ['5.2', 'Order lifecycle across customer, server, kitchen and rider'],
      ['5.3', 'Use-case diagram showing the four roles and the guest participant'],
      ['5.4', 'Level-1 data flow diagram'],
      ['5.5', 'Entity-relationship diagram of the twelve database tables'],
      ['5.6', 'Block diagram of the kitchen-load delivery time estimator'],
      ['6.1', 'Customer landing page with live kitchen-load meters'],
      ['6.2', 'Menu page with the itemised delivery estimate'],
      ['6.3', 'Live group cart with two members and the automatic bill split'],
      ['6.4', 'Order tracking page showing the promise and the status timeline'],
      ['6.5', 'Restaurant kitchen console with live tickets'],
      ['6.6', 'Restaurant insights page reporting promise accuracy'],
      ['6.7', 'Administrator overview of the platform'],
      ['7.1', 'Promised versus actual delivery time by restaurant'],
      ['7.2', 'Distribution of the difference between actual and promised time'],
      ['7.3', 'Median server response time before and after deployment tuning'],
    ],
    [0.14, 0.86],
  ),
  PAGEBREAK(),

  /* -------------------------------------------------------- list of tables */
  H1('List of Tables'),
  TABLE(
    ['Table', 'Caption'],
    [
      ['4.1', 'Comparison of existing food ordering systems with the proposed system'],
      ['5.1', 'Tools and technologies used, with their role in the system'],
      ['5.2', 'Database tables and their purpose'],
      ['6.1', 'Module-wise implementation summary'],
      ['6.2', 'Representative API endpoints'],
      ['7.1', 'Promise accuracy by restaurant'],
      ['7.2', 'Measured server response times'],
      ['7.3', 'Objectives stated in Chapter 3 against outcomes achieved'],
    ],
    [0.14, 0.86],
  ),
  PAGEBREAK(),
];
