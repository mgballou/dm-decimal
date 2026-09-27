// Writes fixtures/differential.json: seeded inputs run through break_eternity.js,
// with every result recorded as sign, layer and mag for the PHP test to match.
//
// Run it with Node 22 on x64. Node 24 sends Math.pow to the platform's libm,
// and Node 22's arm64 build fuses multiply-adds; both change pow10 results.

import { writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import Decimal from 'break_eternity.js';

const SEED = 0x0d3ca1;
const OUT = fileURLToPath(new URL('../../fixtures/differential.json', import.meta.url));
const VERSION = createRequire(import.meta.url)('break_eternity.js/package.json').version;

if (!process.version.startsWith('v22.') || process.arch !== 'x64') {
    console.error(`Need Node 22 on x64, got ${process.version} on ${process.arch}.`);
    process.exit(1);
}

// mulberry32: small, seeded, and the same on every platform.
let state = SEED;
function rand() {
    state = (state + 0x6d2b79f5) | 0;
    let t = Math.imul(state ^ (state >>> 15), 1 | state);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
}

const uniform = (lo, hi) => lo + (hi - lo) * rand();
const sign = () => (rand() < 0.25 ? -1 : 1);

// Each kind draws one input. Layer 0 spans 1e-15 to 9e15, layer 1 reaches
// 1e1e12 and 1e-1e12, and layer 2 reaches 1e1e1e5.
const KINDS = {
    L0: () => Decimal.fromNumber(sign() * 10 ** uniform(-15, 15.95)),
    small: () => Decimal.fromNumber(uniform(-400, 400)),
    int: () => Decimal.fromNumber(Math.round(uniform(-12, 12))),
    L1: () => Decimal.fromComponents(sign(), 1, (rand() < 0.2 ? -1 : 1) * 10 ** uniform(1.21, 12)),
    L2: () => Decimal.fromComponents(sign(), 2, (rand() < 0.2 ? -1 : 1) * 10 ** uniform(1.21, 5)),
};

const EDGES = {
    zero: Decimal.fromNumber(0),
    one: Decimal.fromNumber(1),
    'minus-one': Decimal.fromNumber(-1),
    half: Decimal.fromNumber(0.5),
    'minus-2.5': Decimal.fromNumber(-2.5),
    '9e15': Decimal.fromNumber(9e15),
    '9.000000000000002e15': Decimal.fromNumber(9.000000000000002e15),
    '1e-308': Decimal.fromNumber(1e-308),
    '5e-324': Decimal.fromNumber(5e-324),
    '1e308': Decimal.fromNumber(1e308),
    'minus-1e308': Decimal.fromNumber(-1e308),
    'max-double': Decimal.fromNumber(Number.MAX_VALUE),
    '1e309': Decimal.fromComponents(1, 1, 309),
    'minus-1e400': Decimal.fromComponents(-1, 1, 400),
    '1e-400': Decimal.fromComponents(1, 1, -400),
    '1e1e20': Decimal.fromComponents(1, 2, 20),
    infinity: Decimal.fromNumber(Infinity),
};

// JSON has no NaN, Infinity or -0, so those travel as strings.
function num(x) {
    if (Number.isNaN(x)) return 'NaN';
    if (x === Infinity) return 'Infinity';
    if (x === -Infinity) return '-Infinity';
    if (Object.is(x, -0)) return '-0';
    return x;
}

const triple = (d) => [num(d.sign), num(d.layer), num(d.mag)];

const OPS = {
    cmp: (a, b) => a.cmp(b),
    add: (a, b) => a.add(b),
    sub: (a, b) => a.sub(b),
    mul: (a, b) => a.mul(b),
    div: (a, b) => a.div(b),
    pow: (a, b) => a.pow(b),
    log10: (a) => a.log10(),
    pow10: (a) => Decimal.pow10(a),
    floor: (a) => a.floor(),
    ceil: (a) => a.ceil(),
};

const cases = [];

function record(name, op, a, b) {
    const result = b === undefined ? OPS[op](a) : OPS[op](a, b);
    const entry = { name, op, a: triple(a) };
    if (b !== undefined) entry.b = triple(b);
    entry.want = typeof result === 'number' ? result : triple(result);
    cases.push(entry);
}

const pad = (i) => String(i).padStart(3, '0');

// A second operand close to the first: equal, one digit in 1e10 away, or
// negated, so cmp sees ties and add and sub see cancellation.
function near(a) {
    const r = rand();
    if (r < 0.3) return a;
    if (r < 0.6) return a.neg();
    return Decimal.fromComponents(a.sign, a.layer, a.mag * (1 + uniform(-1e-10, 1e-10)));
}

// A layer 0 operand 15 to 19 digits away from the first, around add's cutoff.
const apart = (a) => a.mul(Decimal.fromNumber(10 ** uniform(15, 19)));

const BINARY_KINDS = ['L0', 'L1', 'L2'];

for (const op of ['cmp', 'add', 'sub', 'mul', 'div']) {
    for (const ka of BINARY_KINDS) {
        for (const kb of BINARY_KINDS) {
            for (let i = 0; i < 40; i++) {
                record(`${op}/${ka}-${kb}/${pad(i)}`, op, KINDS[ka](), KINDS[kb]());
            }
        }
    }
    for (const ka of BINARY_KINDS) {
        for (let i = 0; i < 20; i++) {
            const a = KINDS[ka]();
            record(`${op}/${ka}-near/${pad(i)}`, op, a, near(a));
        }
    }
    for (let i = 0; i < 40; i++) {
        const a = KINDS.L0();
        record(`${op}/L0-apart/${pad(i)}`, op, a, apart(a));
    }
}

for (const kb of ['int', 'small', 'L0', 'L1']) {
    for (const ka of ['L0', 'L1', 'L2']) {
        for (let i = 0; i < 30; i++) {
            const b = kb === 'small' ? Decimal.fromNumber(uniform(-5, 5)) : KINDS[kb]();
            record(`pow/${ka}-${kb}/${pad(i)}`, 'pow', KINDS[ka](), b);
        }
    }
}

for (const op of ['log10', 'pow10', 'floor', 'ceil']) {
    for (const ka of ['L0', 'small', 'L1', 'L2']) {
        for (let i = 0; i < 90; i++) {
            record(`${op}/${ka}/${pad(i)}`, op, KINDS[ka]());
        }
    }
}

for (const op of Object.keys(OPS)) {
    for (const [na, a] of Object.entries(EDGES)) {
        if (OPS[op].length === 1) {
            record(`${op}/edge/${na}`, op, a);
            continue;
        }
        for (const [nb, b] of Object.entries(EDGES)) {
            record(`${op}/edge/${na},${nb}`, op, a, b);
        }
    }
}

const header = {
    note: 'Generated by tools/differential/generate.mjs; do not edit by hand.',
    breakEternity: VERSION,
    node: process.version,
    arch: process.arch,
    seed: SEED,
    count: cases.length,
};

// One case per line keeps a regenerated fixture's diff readable.
const body = cases.map((c) => '        ' + JSON.stringify(c)).join(',\n');
const json = JSON.stringify({ ...header, cases: [] }, null, 4).replace(
    '"cases": []',
    `"cases": [\n${body}\n    ]`,
);
writeFileSync(OUT, json + '\n');
console.log(`Wrote ${cases.length} cases to ${OUT}`);
