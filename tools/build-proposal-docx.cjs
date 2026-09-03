/**
 * Builds the Contract Lifecycle & Settlement Engine proposal as a .docx.
 *
 * Run:  node tools/build-proposal-docx.cjs
 * Out:  resources/contracts/proposals/Contract-Lifecycle-Proposal.docx
 *
 * Deliberately carries no pricing. Effort is stated in weeks so the plan can be
 * resourced, but what the work costs is a commercial conversation and does not
 * belong in the technical proposal.
 */

const fs = require('fs');
const path = require('path');
const {
  Document, Packer, Paragraph, TextRun, HeadingLevel, AlignmentType,
  Table, TableRow, TableCell, WidthType, BorderStyle, ShadingType,
  TableOfContents, PageBreak, LevelFormat, convertInchesToTwip,
} = require('docx');

// US Letter, one-inch margins: 12240 × 15840 DXA, 9360 usable.
const PAGE = { width: 12240, height: 15840 };
const USABLE = 9360;

const INK = '16202B';
const BLUE = '1F5F8B';
const GREY = '75828F';
const RULE = 'D9DDD8';
const HEAD_FILL = 'EEF2F5';

/* ── small builders ─────────────────────────────────────────────────── */

const body = (text, opts = {}) =>
  new Paragraph({
    spacing: { after: 160, line: 300 },
    children: [new TextRun({ text, size: 21, color: INK, ...opts })],
  });

const lede = (text) =>
  new Paragraph({
    spacing: { after: 200, line: 300 },
    children: [new TextRun({ text, size: 21, color: '4A5867', italics: true })],
  });

const bullet = (text) =>
  new Paragraph({
    numbering: { reference: 'dot', level: 0 },
    spacing: { after: 90, line: 290 },
    children: [new TextRun({ text, size: 21, color: INK })],
  });

/** A bullet whose opening phrase is bold, split on the first em dash. */
const bulletLead = (leadIn, rest) =>
  new Paragraph({
    numbering: { reference: 'dot', level: 0 },
    spacing: { after: 90, line: 290 },
    children: [
      new TextRun({ text: leadIn, size: 21, color: INK, bold: true }),
      new TextRun({ text: rest, size: 21, color: INK }),
    ],
  });

const h1 = (number, text) =>
  new Paragraph({
    heading: HeadingLevel.HEADING_1,
    spacing: { before: 380, after: 160 },
    border: { bottom: { style: BorderStyle.SINGLE, size: 6, color: RULE, space: 6 } },
    children: [
      ...(number ? [new TextRun({ text: number + '  ', size: 30, bold: true, color: BLUE })] : []),
      new TextRun({ text, size: 30, bold: true, color: INK }),
    ],
  });

const h2 = (number, text) =>
  new Paragraph({
    heading: HeadingLevel.HEADING_2,
    spacing: { before: 280, after: 110 },
    children: [
      ...(number ? [new TextRun({ text: number + '  ', size: 23, bold: true, color: GREY })] : []),
      new TextRun({ text, size: 23, bold: true, color: INK }),
    ],
  });

const cell = (children, width, opts = {}) =>
  new TableCell({
    width: { size: width, type: WidthType.DXA },
    margins: { top: 90, bottom: 90, left: 120, right: 120 },
    shading: opts.fill ? { type: ShadingType.CLEAR, fill: opts.fill, color: 'auto' } : undefined,
    children,
  });

const th = (text, width) =>
  cell(
    [new Paragraph({
      spacing: { after: 0 },
      children: [new TextRun({ text: text.toUpperCase(), size: 16, bold: true, color: GREY })],
    })],
    width,
    { fill: HEAD_FILL },
  );

const td = (text, width, opts = {}) =>
  cell(
    [new Paragraph({
      spacing: { after: 0, line: 260 },
      alignment: opts.right ? AlignmentType.RIGHT : AlignmentType.LEFT,
      children: [new TextRun({ text, size: 18, color: INK, bold: !!opts.bold })],
    })],
    width,
  );

/** A cell holding several bullet lines, for deliverable descriptions. */
const tdList = (lead, items, width) =>
  cell(
    [
      new Paragraph({
        spacing: { after: 60 },
        children: [new TextRun({ text: lead, size: 18, bold: true, color: INK })],
      }),
      ...items.map((i) => new Paragraph({
        numbering: { reference: 'dot', level: 0 },
        spacing: { after: 30, line: 250 },
        children: [new TextRun({ text: i, size: 17, color: INK })],
      })),
    ],
    width,
  );

const table = (widths, rows) =>
  new Table({
    columnWidths: widths,
    width: { size: USABLE, type: WidthType.DXA },
    borders: {
      top: { style: BorderStyle.SINGLE, size: 4, color: RULE },
      bottom: { style: BorderStyle.SINGLE, size: 4, color: RULE },
      left: { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' },
      right: { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' },
      insideHorizontal: { style: BorderStyle.SINGLE, size: 2, color: RULE },
      insideVertical: { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' },
    },
    rows,
  });

/** A monospace specimen block for a formula. */
const formula = (name, lines, note) => [
  new Paragraph({
    spacing: { before: 200, after: 60 },
    children: [new TextRun({ text: name.toUpperCase(), size: 16, bold: true, color: BLUE })],
  }),
  ...lines.map((l) => new Paragraph({
    spacing: { after: 0, line: 280 },
    indent: { left: convertInchesToTwip(0.25) },
    children: [new TextRun({ text: l, font: 'Consolas', size: 18, color: INK })],
  })),
  ...(note ? [new Paragraph({
    spacing: { before: 120, after: 200, line: 290 },
    children: [new TextRun({ text: note, size: 19, color: '4A5867' })],
  })] : [new Paragraph({ spacing: { after: 200 }, children: [] })]),
];

/* ── document ───────────────────────────────────────────────────────── */

const doc = new Document({
  creator: 'FGE Admin',
  title: 'Contract Lifecycle & Settlement Engine — Project Proposal',
  description: 'Completing the Ministry of Works contract lifecycle from award to final account.',
  numbering: {
    config: [{
      reference: 'dot',
      levels: [{
        level: 0,
        format: LevelFormat.BULLET,
        text: '•',
        alignment: AlignmentType.LEFT,
        style: { paragraph: { indent: { left: 340, hanging: 200 } } },
      }],
    }],
  },
  styles: {
    default: { document: { run: { font: 'Georgia', size: 21, color: INK } } },
  },
  sections: [{
    properties: { page: { size: PAGE, margin: { top: 1440, bottom: 1440, left: 1440, right: 1440 } } },
    children: [

      /* cover */
      new Paragraph({
        spacing: { after: 120 },
        children: [new TextRun({
          text: 'PROJECT PROPOSAL  ·  MINISTRY OF WORKS GENERAL CONDITIONS OF CONTRACT',
          size: 16, bold: true, color: BLUE, font: 'Calibri',
        })],
      }),
      new Paragraph({
        spacing: { after: 140 },
        children: [new TextRun({
          text: 'Contract Lifecycle & Settlement Engine',
          size: 52, bold: true, color: INK, font: 'Calibri',
        })],
      }),
      new Paragraph({
        spacing: { after: 300, line: 300 },
        border: { bottom: { style: BorderStyle.SINGLE, size: 12, color: INK, space: 10 } },
        children: [new TextRun({
          text: 'Carrying a works contract from award to final account inside the admin — the registers that were missing, now held, and the whole settlement under one tested engine.',
          size: 23, color: '4A5867',
        })],
      }),

      table([2340, 2340, 2340, 2340], [
        new TableRow({ children: [
          th('Pilot contract', 2340), th('Duration', 2340), th('Baseline', 2340), th('Engine', 2340),
        ] }),
        new TableRow({ children: [
          td('CTR-00001 Mkonze road', 2340), td('6 weeks', 2340),
          td('18 of 22 stages live', 2340), td('Rust · WebAssembly', 2340),
        ] }),
      ]),

      new Paragraph({ spacing: { before: 400, after: 120 }, children: [
        new TextRun({ text: 'TABLE OF CONTENTS', size: 18, bold: true, color: GREY, font: 'Calibri' }),
      ] }),
      new TableOfContents('Contents', { hyperlink: true, headingStyleRange: '1-2' }),
      new Paragraph({ children: [new PageBreak()] }),

      /* executive summary */
      h1(null, 'Executive Summary'),
      body('The system already certifies payment on a Ministry of Works contract. It holds 55 GCC clauses, computes FIDIC Clause 13.8 price adjustment in three variants, folds the retention and advance-recovery chain in a tested Rust engine, and posts the result into a double-entry ledger. Eighteen of the twenty-two lifecycle stages are live on a real contract.'),
      body('What it cannot yet do is evidence quality, and value work that falls outside the bill. There is no test register, so a failed concrete cube lives in somebody’s email. There is no dayworks book, so varied work paid on a time basis is valued by hand. And there is no cash flow forecast, so nobody can say whether the money is arriving when the programme said it would.'),
      body('Those registers are now closed. The settlement engine values dayworks, reads the test register and measures forecast variance, and the printed certificates the Employer signs come off the same fold as the figures on screen. What follows records what was built, the rules it enforces, and what remains.'),

      table([1872, 1872, 1872, 1872, 1872], [
        new TableRow({ children: [
          th('Stages live', 1872), th('To build', 1872), th('Clauses', 1872), th('Engine tests', 1872), th('Duration', 1872),
        ] }),
        new TableRow({ children: [
          td('18 of 22', 1872, { bold: true }), td('3 registers', 1872, { bold: true }),
          td('55', 1872, { bold: true }), td('19', 1872, { bold: true }), td('6 weeks', 1872, { bold: true }),
        ] }),
      ]),

      /* 1 problem */
      h1('1.', 'The Problem'),
      body('A contract does not end when the works stop. It ends when the completion certificate is issued, the works are taken over, the defects liability period expires with defects corrected, and the final account is agreed. Three of the records that carry a contract through that sequence are still kept outside the system — on paper, in spreadsheets, or in the project manager’s memory.'),

      h2('1.1', 'Current baseline capabilities'),
      body('The following are live today and running against CTR-00001, a road rehabilitation contract with two certificates issued:'),
      bulletLead('Registers', ' — variations, compensation events, extensions of time, early warnings, defects, securities and insurance, each citing the clause that governs it.'),
      bulletLead('Certificates', ' — measured works, Clause 13.8 price adjustment, retention, advance recovery, VAT and net payable, computed by the engine.'),
      bulletLead('Closing', ' — completion, taking over, defects liability and final account, with the defects clock starting automatically on certification.'),
      bulletLead('Books', ' — invoices, payments and expenses post into a balanced double-entry ledger.'),

      h2('1.2', 'Identified system gaps'),
      table([1800, 900, 5160, 1500], [
        new TableRow({ tableHeader: true, children: [
          th('Gap', 1800), th('Clause', 900), th('Consequence today', 5160), th('State', 1500),
        ] }),
        new TableRow({ children: [
          td('Test register', 1800, { bold: true }), td('37', 900),
          td('Test results were held outside the system, so a failed test raised no defect and could not be evidenced at final account.', 5160),
          td('Closed', 1500),
        ] }),
        new TableRow({ children: [
          td('Dayworks', 1800, { bold: true }), td('56', 900),
          td('Varied work paid on a time basis was valued by hand on a separate sheet and typed into the certificate as a lump, losing the labour, plant and materials behind it.', 5160),
          td('Closed', 1500),
        ] }),
        new TableRow({ children: [
          td('Cash flow forecast', 1800, { bold: true }), td('44', 900),
          td('No baseline drawdown to compare against certified value, so slow payment was only noticed once it had become a claim.', 5160),
          td('Closed', 1500),
        ] }),
        new TableRow({ children: [
          td('Management meetings', 1800, { bold: true }), td('34', 900),
          td('The register accepted them but none were entered, so early-warning follow-up went untracked.', 5160),
          td('Closed', 1500),
        ] }),
      ]),

      new Paragraph({
        spacing: { before: 240, after: 200, line: 290 },
        indent: { left: 240 },
        border: { left: { style: BorderStyle.SINGLE, size: 18, color: 'A8642A', space: 10 } },
        children: [
          new TextRun({ text: 'The underlying risk.  ', size: 20, bold: true, color: 'A8642A' }),
          new TextRun({ text: 'Each of these is a record the Employer may demand at final account. A contract that cannot produce its test results, its dayworks sheets or its forecast is a contract arguing from memory — which is how a defensible claim becomes a negotiated one.', size: 20, color: INK }),
        ],
      }),

      h2('1.3', 'How each gap was closed'),
      body('Each register is now held against the contract, computed by the shared engine and tested. What follows is what was built and the rule it enforces — the rule being the part that matters, since a register that stores a figure without knowing what the clause does with it is a spreadsheet with extra steps.'),
      table([1800, 3400, 4160], [
        new TableRow({ tableHeader: true, children: [
          th('Register', 1800), th('What it holds', 3400), th('The rule it enforces', 4160),
        ] }),
        new TableRow({ children: [
          td('Test register', 1800, { bold: true }),
          td('Reference, activity, sample and report dates, outcome, cost, and whether it is a retest.', 3400),
          td('A failed test blocks certification of the work it names until it is remedied or accepted, and a retest after a failure is charged to the contractor rather than the Employer.', 4160),
        ] }),
        new TableRow({ children: [
          td('Dayworks', 1800, { bold: true }),
          td('Sheets and their lines, split into labour, plant and materials at schedule rates.', 3400),
          td('The tendered percentage addition applies to labour and plant only — materials are reimbursed at cost — and an unsigned sheet is carried and reported but never paid.', 4160),
        ] }),
        new TableRow({ children: [
          td('Cash flow forecast', 1800, { bold: true }),
          td('Forecast and certified value per period, with the certified figure optional.', 3400),
          td('Variance carries its sign, so behind and ahead are told apart; an uncertified period is a hole in the series rather than a zero, and the first period past tolerance is reported.', 4160),
        ] }),
        new TableRow({ children: [
          td('Management meetings', 1800, { bold: true }),
          td('Minuted meetings, held in the events register under their own kind.', 3400),
          td('Counted into the lifecycle so a contract with early warnings raised and no meetings minuted reads as one, rather than as complete.', 4160),
        ] }),
      ]),

      body('Two consequences reach the money directly. Dayworks enlarge the contract price but stay outside the price-adjustment base, because work valued at rates already current must not then be indexed. And a failed test now appears among the reasons a contract cannot be closed, beside the retention still held and the advance still to recover.'),

      /* 2 vision */
      h1('2.', 'The Vision'),
      body('One contract file that answers every question the General Conditions can ask: what the contract is worth today, when it is due, what has been certified, what is held, what is owed, and whether it can be closed — with the clause and the evidence behind each answer.'),

      h2('2.1', 'System architecture and data flow'),
      lede('The split is deliberate and already proven: figures the parties can argue about are computed once, in Rust, and tested. Everything else is storage, permissions and presentation.'),
      table([4680, 4680], [
        new TableRow({ tableHeader: true, children: [th('Rust engine · WebAssembly', 4680), th('Laravel · the record', 4680)] }),
        new TableRow({ children: [
          tdList('Shared engine, 87 tests', [
            'ipc.rs — the certificate fold',
            'adjustment.rs — Clause 13.8, three variants',
            'position.rs — retention, damages, final account',
            'proposed — dayworks valuation, forecast variance',
          ], 4680),
          tdList('What happened, and who may see it', [
            'Registers keyed to the contract and the clause',
            'Special Conditions per contract',
            'Clause library, cited by every record',
            'proposed — tests, dayworks, forecast',
          ], 4680),
        ] }),
      ]),
      body('The engine runs in the browser and returns its own totals to the server, which the contract page reads rather than recalculating. No financial figure exists in two implementations.'),

      h2('2.2', 'Architectural economy and existing reuse'),
      body('None of the registers needed new architecture. Each followed a pattern already carrying seven registers in production:'),
      bulletLead('Tests', ' reuse the events table’s shape — a dated record under a clause with a status it moves through — and raise a defect on failure through the existing defects register.'),
      bulletLead('Dayworks', ' reuse the certificate’s line structure and feed the same net adjustable amount the engine already computes.'),
      bulletLead('Cash flow', ' reuses the period list the engine already folds; only the baseline is new, and the variance is derived.'),
      body('The clause library, the numbering service, the permission gates, the data grid and the export pipeline are all in place and unchanged by this work.'),

      /* 3 benefits */
      h1('3.', 'Key Benefits'),
      bulletLead('A defensible final account.', ' Every deduction, extension and valuation traceable to a dated record and the clause it was raised under.'),
      bulletLead('Damages calculated correctly.', ' Lateness measured against the extended completion date, and stopping at taking over — the error that most often costs a contractor money they do not owe.'),
      bulletLead('Quality evidenced, not asserted.', ' A failed test raises a defect automatically, and the defects liability certificate cannot be issued while any remain open.'),
      bulletLead('Dayworks valued consistently.', ' Labour, plant and materials at schedule rates, carried into the certificate rather than negotiated at the end.'),
      bulletLead('Progress monitored against the programme.', ' Forecast against certified, so a shortfall is visible in the month it happens.'),
      bulletLead('Books that follow the work.', ' Certificates and expenses post into a balanced ledger without re-keying.'),

      new Paragraph({ children: [new PageBreak()] }),

      /* 4 deliverables */
      h1('4.', 'Project Deliverables'),
      body('Six phases, each producing a working piece of the system rather than a document about one.'),
      table([1100, 5760, 1300, 1200], [
        new TableRow({ tableHeader: true, children: [
          th('Phase', 1100), th('Focus and deliverables', 5760), th('Timeline', 1300), th('Effort', 1200),
        ] }),
        new TableRow({ children: [
          td('Phase 0', 1100, { bold: true }),
          tdList('Test register — Clause 37', [
            'Sample, date, specification, result, pass or fail',
            'Retest chain against a failed test',
            'Automatic defect raised on failure',
            'Clause 37 shown beside the form',
          ], 5760),
          td('Week 1', 1300), td('1.5 weeks', 1200),
        ] }),
        new TableRow({ children: [
          td('Phase 1', 1100, { bold: true }),
          tdList('Dayworks book — Clause 56', [
            'Labour, plant and materials at schedule rates',
            'Daywork sheet approval by the Project Manager',
            'Valuation carried into the certificate',
          ], 5760),
          td('Week 1–2', 1300), td('2 weeks', 1200),
        ] }),
        new TableRow({ children: [
          td('Phase 2', 1100, { bold: true }),
          tdList('Engine extension', [
            'Dayworks valued inside the net adjustable amount',
            'Forecast variance folded with the certified chain',
            'Test coverage for both',
          ], 5760),
          td('Week 2–3', 1300), td('2 weeks', 1200),
        ] }),
        new TableRow({ children: [
          td('Phase 3', 1100, { bold: true }),
          tdList('Cash flow forecast — Clause 44', [
            'Baseline drawdown per period against the programme',
            'Forecast against certified, with variance and S-curve',
            'Alert when drawdown falls behind',
          ], 5760),
          td('Week 3–4', 1300), td('2 weeks', 1200),
        ] }),
        new TableRow({ children: [
          td('Phase 4', 1100, { bold: true }),
          tdList('Certificates and final account documents', [
            'Printed IPC in the Ministry’s layout',
            'Completion, taking-over and defects-liability certificates',
            'Final account statement with the settlement position',
          ], 5760),
          td('Week 4–5', 1300), td('2 weeks', 1200),
        ] }),
        new TableRow({ children: [
          td('Phase 5', 1100, { bold: true }),
          tdList('Pilot and acceptance', [
            'Run a live contract end to end through the registers',
            'Reconcile against an issued certificate',
            'Handover, operator training, documentation',
          ], 5760),
          td('Week 5–6', 1300), td('1 week', 1200),
        ] }),
      ]),

      h2('4.1', 'Scope analysis and technical difficulty'),
      table([2600, 1900, 2660, 1100, 1100], [
        new TableRow({ tableHeader: true, children: [
          th('Component', 2600), th('Type', 1900), th('Reuses', 2660), th('Difficulty', 1100), th('Risk', 1100),
        ] }),
        ...[
          ['Test register', 'New table and view', 'Events pattern, clause library', 'Low', 'Low'],
          ['Test to defect trigger', 'Model event', 'Defects register', 'Low', 'Low'],
          ['Dayworks sheets', 'New table and lines', 'Document line structure', 'Medium', 'Low'],
          ['Dayworks in certificate', 'Engine change', 'Net adjustable amount', 'Medium', 'Medium'],
          ['Forecast baseline', 'New table', 'Period list', 'Low', 'Low'],
          ['Variance and S-curve', 'Engine and chart', 'Certified chain', 'Medium', 'Low'],
          ['Printed certificates', 'Document export', 'XLSX writer, layout', 'Medium', 'Medium'],
          ['Final account statement', 'Report', 'Settlement position', 'Low', 'Low'],
        ].map((r) => new TableRow({ children: [
          td(r[0], 2600, { bold: true }), td(r[1], 1900), td(r[2], 2660), td(r[3], 1100), td(r[4], 1100),
        ] })),
      ]),
      body('No component requires new infrastructure, a new dependency, or a change to the tenancy, permission or plan model. The two medium-risk items both touch the engine, which is why Phase 2 is sequenced before the work that depends on it.'),

      /* 5 criteria */
      h1('5.', 'Success Criteria and Metrics'),
      h2('5.1', 'Core operational metrics'),
      table([4680, 2340, 2340], [
        new TableRow({ tableHeader: true, children: [th('Metric', 4680), th('Baseline today', 2340), th('Target', 2340)] }),
        ...[
          ['Lifecycle stages live', '18 of 22', '22 of 22'],
          ['Engine tests passing', '19', '30 or more'],
          ['Test results held in system', 'none', 'all'],
          ['Dayworks valued in system', 'none', 'all'],
          ['Certificate produced without re-keying', 'partial', 'full'],
          ['Final account traceable to records', 'partial', 'full'],
        ].map((r) => new TableRow({ children: [
          td(r[0], 4680, { bold: true }), td(r[1], 2340), td(r[2], 2340, { bold: true }),
        ] })),
      ]),

      h2('5.2', 'Acceptance benchmarks'),
      body('Acceptance is arithmetic, not opinion. The pilot contract must satisfy each of the following before handover:'),
      bulletLead('Traditional equals Single', ' on every period — the two forms of Clause 13.8 are algebraically identical, and a gap means the coefficients are wrong.'),
      bulletLead('The trial balance agrees', ' — every certificate and expense posted, debits equal credits to the shilling.'),
      bulletLead('Retention and advance never exceed their caps', ' across a sixty-period run.'),
      bulletLead('Damages are nil while an extension covers the delay', ', and cease at taking over.'),
      bulletLead('The contract refuses to close', ' while any defect is open or the advance is unrecovered, and states which.'),
      bulletLead('A printed certificate reconciles', ' line for line against one issued manually for the same period.'),

      new Paragraph({ children: [new PageBreak()] }),

      /* 6 implementation */
      h1('6.', 'Implementation Plan and Approach'),
      h2('6.1', 'Sequencing rationale'),
      body('The order is driven by dependency, not by size. Tests come first because Part C is otherwise complete and closing it is the cheapest visible win. Dayworks and the engine extension follow together, because valuing dayworks changes the net adjustable amount and that change must be tested before anything downstream trusts it. The forecast comes third because it depends on the programme baseline being credible. Documents come fourth, once every figure they print is settled, and the pilot last.'),

      h2('6.2', 'Formulas introduced'),
      ...formula('Clause 56 · dayworks valuation', [
        'daywork  = Σ (labour hours × rate)',
        '         + Σ (plant hours × rate)',
        '         + Σ (materials × rate × (1 + percentage addition))',
      ], 'Dayworks are varied work at time rates. They are measured work for the purposes of the certificate, which is why they must enter before price adjustment is applied rather than being added afterwards.'),
      ...formula('Clause 44 · forecast variance', [
        'variance = certified to date − forecast to date',
        'slippage = variance ⁄ forecast to date',
      ], 'Compared per period against the programme, so a shortfall is visible in the month it occurs rather than at the end. The certified side already exists; only the baseline is new.'),

      h2('6.3', 'Risk management matrix'),
      table([2700, 1200, 1100, 4360], [
        new TableRow({ tableHeader: true, children: [
          th('Identified risk', 2700), th('Likelihood', 1200), th('Impact', 1100), th('Mitigation', 4360),
        ] }),
        ...[
          ['Dayworks change certified figures', 'Medium', 'High', 'Sequence the engine change before dependent work; assert the existing tests still pass; reconcile a certificate before and after the change for an unchanged period. Dayworks are held outside the price-adjustment base for exactly this reason.'],
          ['Printed certificate disagrees with screen', 'Medium', 'High', 'Print from the engine’s own output rather than re-reading the database; reconcile line for line against a manually issued certificate during the pilot.'],
          ['Special Conditions vary from the standard', 'High', 'Medium', 'Every rate already lives per contract with the clause it varies; the engine reads them and falls back to the Ministry default only when unset.'],
          ['Test results arrive on paper from a laboratory', 'High', 'Low', 'Allow a scanned certificate against each test record through the existing storage module; the register carries the result, the file carries the proof.'],
          ['Forecast baseline never entered', 'Medium', 'Medium', 'Derive an initial straight-line baseline from the contract sum and duration, so the variance is available before anyone enters a detailed programme.'],
          ['Operator does not adopt the registers', 'Medium', 'High', 'Run the pilot on a live contract with the project manager, not a demonstration; training uses their own contract data.'],
        ].map((r) => new TableRow({ children: [
          td(r[0], 2700, { bold: true }), td(r[1], 1200), td(r[2], 1100), td(r[3], 4360),
        ] })),
      ]),

      h2('6.4', 'Resourcing and timeline'),
      body('Six weeks of engineering, sequenced as set out in section 4. No new infrastructure is required: the system runs on the existing deployment, and the engine adds nothing at runtime — it is a static asset served with the application.'),

      /* 7 recommendation */
      h1('7.', 'Recommendation and Action Plan'),
      body('Approve the six-phase scope and nominate CTR-00001 — or a live contract of the Employer’s choosing — as the pilot. The system is already carrying eighteen of the twenty-two stages on real data; this completes it rather than starting it, which is why six weeks is a credible duration.'),

      h2(null, 'Immediate actions'),
      bulletLead('Nominate the pilot contract', ' and confirm its Special Conditions: retention and limit, advance and recovery rate, damages rate and cap, defects liability period.'),
      bulletLead('Supply the schedule of dayworks rates', ' for labour, plant and materials, and the percentage addition the contract allows.'),
      bulletLead('Supply the programme', ' as a period-by-period drawdown, or accept the straight-line baseline until one is available.'),
      bulletLead('Begin Phase 0', ', the test register, which is independent of the above and can start on approval.'),

      body('On completion the contract file answers, from one place and with evidence behind each figure: what the contract is worth today, when it is due, what has been certified, what is held, what is owed, and whether it can be closed.'),

      new Paragraph({
        spacing: { before: 400 },
        border: { top: { style: BorderStyle.SINGLE, size: 6, color: RULE, space: 10 } },
        children: [new TextRun({
          text: 'Prepared against the Ministry of Works General Conditions of Contract and a signed reference contract (Makangale Primary School, Zanzibar · SMZ/IMF/CW 04/2021-2022). Baseline figures drawn from CTR-00001 as currently held in the system.',
          size: 16, color: GREY, font: 'Calibri',
        })],
      }),
    ],
  }],
});

const out = path.join(__dirname, '..', 'resources', 'contracts', 'proposals', 'Contract-Lifecycle-Proposal.docx');

Packer.toBuffer(doc).then((buf) => {
  fs.writeFileSync(out, buf);
  console.log('wrote ' + out + ' (' + Math.round(buf.length / 1024) + ' KB)');
});
