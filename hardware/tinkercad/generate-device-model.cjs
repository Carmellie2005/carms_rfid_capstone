const fs = require('fs');
const path = require('path');
const zlib = require('zlib');

const outDir = __dirname;
const objPath = path.join(outDir, 'slsu-bc-patrol-device-model.obj');
const mtlPath = path.join(outDir, 'slsu-bc-patrol-device-model.mtl');
const stlPath = path.join(outDir, 'slsu-bc-patrol-device-model.stl');
const readmePath = path.join(outDir, 'README.md');
const zipPath = path.join(outDir, 'slsu-bc-patrol-device-model.zip');

let vertices = [];
let faces = [];
let stlTriangles = [];
let currentMaterial = null;

function v(x, y, z) {
  vertices.push([round(x), round(y), round(z)]);
  return vertices.length;
}

function round(value) {
  return Math.round(value * 1000) / 1000;
}

function use(material) {
  if (currentMaterial !== material) {
    faces.push(`usemtl ${material}`);
    currentMaterial = material;
  }
}

function quad(a, b, c, d) {
  faces.push(`f ${a} ${b} ${c} ${d}`);
  stlTriangles.push([a, b, c], [a, c, d]);
}

function tri(a, b, c) {
  faces.push(`f ${a} ${b} ${c}`);
  stlTriangles.push([a, b, c]);
}

function object(name, material) {
  faces.push(`o ${name}`);
  use(material);
}

function addBox(name, x1, x2, y1, y2, z1, z2, material) {
  object(name, material);
  const p = [
    v(x1, y1, z1), v(x2, y1, z1), v(x2, y2, z1), v(x1, y2, z1),
    v(x1, y1, z2), v(x2, y1, z2), v(x2, y2, z2), v(x1, y2, z2),
  ];

  quad(p[0], p[1], p[2], p[3]);
  quad(p[5], p[4], p[7], p[6]);
  quad(p[4], p[0], p[3], p[7]);
  quad(p[1], p[5], p[6], p[2]);
  quad(p[3], p[2], p[6], p[7]);
  quad(p[4], p[5], p[1], p[0]);
}

function roundedRectPoints(width, height, radius, segments) {
  const w = width / 2;
  const yMin = 0;
  const yMax = height;
  const centers = [
    [w - radius, yMax - radius, 0, Math.PI / 2],
    [-w + radius, yMax - radius, Math.PI / 2, Math.PI],
    [-w + radius, yMin + radius, Math.PI, Math.PI * 1.5],
    [w - radius, yMin + radius, Math.PI * 1.5, Math.PI * 2],
  ];

  const points = [];
  for (const [cx, cy, start, end] of centers) {
    for (let i = 0; i <= segments; i++) {
      const t = start + ((end - start) * i) / segments;
      points.push([cx + Math.cos(t) * radius, cy + Math.sin(t) * radius]);
    }
  }
  return points;
}

function addRoundedRectPrism(name, width, height, depth, radius, material) {
  object(name, material);
  const pts = roundedRectPoints(width, height, radius, 6);
  const zFront = -depth / 2;
  const zBack = depth / 2;
  const front = pts.map(([x, y]) => v(x, y, zFront));
  const back = pts.map(([x, y]) => v(x, y, zBack));
  const cFront = v(0, height / 2, zFront);
  const cBack = v(0, height / 2, zBack);

  for (let i = 0; i < pts.length; i++) {
    const n = (i + 1) % pts.length;
    tri(cFront, front[i], front[n]);
    tri(cBack, back[n], back[i]);
    quad(front[i], back[i], back[n], front[n]);
  }
}

function addCylinder(name, axis, cx, cy, cz, radius, length, material, segments = 36) {
  object(name, material);
  const a = [];
  const b = [];
  const start = -length / 2;
  const end = length / 2;

  for (let i = 0; i < segments; i++) {
    const t = (Math.PI * 2 * i) / segments;
    const u = Math.cos(t) * radius;
    const w = Math.sin(t) * radius;

    if (axis === 'x') {
      a.push(v(cx + start, cy + u, cz + w));
      b.push(v(cx + end, cy + u, cz + w));
    } else if (axis === 'y') {
      a.push(v(cx + u, cy + start, cz + w));
      b.push(v(cx + u, cy + end, cz + w));
    } else {
      a.push(v(cx + u, cy + w, cz + start));
      b.push(v(cx + u, cy + w, cz + end));
    }
  }

  const c1 = axis === 'x'
    ? v(cx + start, cy, cz)
    : axis === 'y'
      ? v(cx, cy + start, cz)
      : v(cx, cy, cz + start);
  const c2 = axis === 'x'
    ? v(cx + end, cy, cz)
    : axis === 'y'
      ? v(cx, cy + end, cz)
      : v(cx, cy, cz + end);

  for (let i = 0; i < segments; i++) {
    const n = (i + 1) % segments;
    quad(a[i], a[n], b[n], b[i]);
    tri(c1, a[n], a[i]);
    tri(c2, b[i], b[n]);
  }
}

function addLineOnFront(name, x1, y1, x2, y2, width, z, depth, material) {
  object(name, material);
  const dx = x2 - x1;
  const dy = y2 - y1;
  const len = Math.hypot(dx, dy);
  if (!len) return;
  const nx = (-dy / len) * (width / 2);
  const ny = (dx / len) * (width / 2);
  const z1 = z;
  const z2 = z - depth;
  const p = [
    v(x1 + nx, y1 + ny, z1),
    v(x2 + nx, y2 + ny, z1),
    v(x2 - nx, y2 - ny, z1),
    v(x1 - nx, y1 - ny, z1),
    v(x1 + nx, y1 + ny, z2),
    v(x2 + nx, y2 + ny, z2),
    v(x2 - nx, y2 - ny, z2),
    v(x1 - nx, y1 - ny, z2),
  ];
  quad(p[0], p[1], p[2], p[3]);
  quad(p[5], p[4], p[7], p[6]);
  quad(p[4], p[0], p[3], p[7]);
  quad(p[1], p[5], p[6], p[2]);
  quad(p[3], p[2], p[6], p[7]);
  quad(p[4], p[5], p[1], p[0]);
}

function addArcOnFront(name, cx, cy, radius, startDeg, endDeg, width, z, material) {
  const steps = 18;
  let px = null;
  let py = null;
  for (let i = 0; i <= steps; i++) {
    const t = (Math.PI / 180) * (startDeg + ((endDeg - startDeg) * i) / steps);
    const x = cx + Math.cos(t) * radius;
    const y = cy + Math.sin(t) * radius;
    if (px !== null) {
      addLineOnFront(`${name}_${i}`, px, py, x, y, width, z, 0.8, material);
    }
    px = x;
    py = y;
  }
}

function addSegmentLetter(char, x, y, scale, material) {
  const w = 5 * scale;
  const h = 8 * scale;
  const mid = y + h / 2;
  const z = -40.9;
  const stroke = 0.7 * scale;
  const line = (id, x1, y1, x2, y2) => addLineOnFront(`label_${char}_${id}_${x}_${y}`, x + x1 * scale, y + y1 * scale, x + x2 * scale, y + y2 * scale, stroke, z, 0.5, material);

  const parts = {
    top: () => line('top', 0, 8, 5, 8),
    mid: () => line('mid', 0, 4, 5, 4),
    bottom: () => line('bottom', 0, 0, 5, 0),
    left: () => line('left', 0, 0, 0, 8),
    right: () => line('right', 5, 0, 5, 8),
    ul: () => line('ul', 0, 4, 0, 8),
    ll: () => line('ll', 0, 0, 0, 4),
    ur: () => line('ur', 5, 4, 5, 8),
    lr: () => line('lr', 5, 0, 5, 4),
    diag1: () => line('diag1', 0, 8, 5, 0),
    diag2: () => line('diag2', 0, 0, 5, 8),
    diagR: () => line('diagR', 0, 4, 5, 0),
  };

  if (char === 'S') ['top', 'ul', 'mid', 'lr', 'bottom'].forEach((p) => parts[p]());
  if (char === 'C') ['top', 'left', 'bottom'].forEach((p) => parts[p]());
  if (char === 'A') ['top', 'ul', 'ur', 'mid', 'll', 'lr'].forEach((p) => parts[p]());
  if (char === 'N') ['left', 'right', 'diag2'].forEach((p) => parts[p]());
  if (char === 'H') ['left', 'right', 'mid'].forEach((p) => parts[p]());
  if (char === 'E') ['top', 'mid', 'bottom', 'left'].forEach((p) => parts[p]());
  if (char === 'R') ['top', 'ul', 'ur', 'mid', 'll', 'diagR'].forEach((p) => parts[p]());
}

function addLabelText(text, x, y, scale, material) {
  let cursor = x;
  for (const char of text) {
    if (char === ' ') {
      cursor += 4 * scale;
      continue;
    }
    addSegmentLetter(char, cursor, y, scale, material);
    cursor += 7 * scale;
  }
}

function addScrew(name, x, y) {
  addCylinder(`${name}_head`, 'z', x, y, -39.7, 4.1, 2.4, 'metal_dark', 28);
  addLineOnFront(`${name}_slot_a`, x - 2, y, x + 2, y, 0.45, -41.05, 0.4, 'metal_light');
  addLineOnFront(`${name}_slot_b`, x, y - 2, x, y + 2, 0.45, -41.05, 0.4, 'metal_light');
}

function addSpeakerHoles() {
  addCylinder('buzzer_outer', 'x', 63, 128, 0, 12, 6, 'black_abs', 36);
  addCylinder('buzzer_inner_plate', 'x', 66.2, 128, 0, 9.2, 0.8, 'metal_dark', 36);
  const holes = [
    [0, 0], [-4, 0], [4, 0], [0, -4], [0, 4], [-3, -3], [3, -3], [-3, 3], [3, 3],
  ];
  holes.forEach(([dy, dz], index) => {
    addCylinder(`buzzer_hole_${index + 1}`, 'x', 66.75, 128 + dy, dz, 1.1, 0.9, 'black_hole', 14);
  });
}

function addBarrelJack() {
  addCylinder('dc_barrel_outer', 'y', 0, 203, 0, 8.5, 6, 'black_abs', 36);
  addCylinder('dc_barrel_metal_ring', 'y', 0, 206.2, 0, 5.3, 1.2, 'metal_light', 36);
  addCylinder('dc_barrel_center', 'y', 0, 207, 0, 2.3, 1.3, 'black_hole', 30);
}

function buildModel() {
  addRoundedRectPrism('white_plastic_enclosure_120x200x75mm', 120, 200, 75, 5, 'white_plastic');

  addBox('front_black_abs_bezel', -53, 53, 79, 157, -40.2, -37.5, 'black_abs');
  addBox('lcd_recess_outer_frame', -39, 39, 105, 135, -41.6, -40.15, 'black_hole');
  addBox('lcd_inner_frame', -35, 35, 109, 131, -42.2, -41.45, 'black_abs');
  addBox('lcd_blue_display', -31.5, 31.5, 112, 128, -42.8, -42.1, 'blue_lcd');
  addBox('clear_acrylic_lcd_cover', -42, 42, 103, 137, -43.25, -42.75, 'clear_acrylic');
  addLineOnFront('acrylic_highlight', -30, 105, -6, 137, 3.2, -43.55, 0.25, 'acrylic_highlight');

  addBox('white_scan_sticker', -43, 43, 20, 68, -40.9, -40.05, 'sticker_white');
  addLineOnFront('sticker_border_top', -41, 66, 41, 66, 1.2, -41.35, 0.45, 'sticker_blue');
  addLineOnFront('sticker_border_bottom', -41, 22, 41, 22, 1.2, -41.35, 0.45, 'sticker_blue');
  addLineOnFront('sticker_border_left', -41, 22, -41, 66, 1.2, -41.35, 0.45, 'sticker_blue');
  addLineOnFront('sticker_border_right', 41, 22, 41, 66, 1.2, -41.35, 0.45, 'sticker_blue');

  addArcOnFront('rfid_wave_1', -28, 48, 7, 120, 240, 1.3, -41.4, 'sticker_blue');
  addArcOnFront('rfid_wave_2', -28, 48, 12, 120, 240, 1.3, -41.4, 'sticker_blue');
  addArcOnFront('rfid_wave_3', -28, 48, 17, 120, 240, 1.3, -41.4, 'sticker_blue');
  addLineOnFront('card_top', -8, 55, 7, 64, 1.5, -41.4, 0.45, 'sticker_blue');
  addLineOnFront('card_right', 7, 64, 18, 47, 1.5, -41.4, 0.45, 'sticker_blue');
  addLineOnFront('card_bottom', 18, 47, 2, 38, 1.5, -41.4, 0.45, 'sticker_blue');
  addLineOnFront('card_left', 2, 38, -8, 55, 1.5, -41.4, 0.45, 'sticker_blue');
  addLineOnFront('hand_index', 8, 44, 26, 34, 1.4, -41.4, 0.45, 'sticker_blue');
  addLineOnFront('hand_thumb', 14, 44, 23, 48, 1.4, -41.4, 0.45, 'sticker_blue');
  addLineOnFront('hand_wrist', 22, 37, 30, 36, 1.4, -41.4, 0.45, 'sticker_blue');
  addLabelText('SCAN HERE', -31, 27, 1.05, 'sticker_blue');

  addScrew('front_screw_top_left', -52, 190);
  addScrew('front_screw_top_right', 52, 190);
  addScrew('front_screw_bottom_left', -52, 10);
  addScrew('front_screw_bottom_right', 52, 10);

  addSpeakerHoles();
  addBarrelJack();

  addLineOnFront('side_case_seam_top', -54, 180, 54, 180, 0.7, -38.6, 0.35, 'case_shadow');
  addLineOnFront('side_case_seam_bottom', -54, 76, 54, 76, 0.7, -38.6, 0.35, 'case_shadow');
}

function materialFile() {
  return [
    'newmtl white_plastic',
    'Ka 0.95 0.93 0.88',
    'Kd 0.92 0.90 0.85',
    'Ks 0.15 0.15 0.15',
    '',
    'newmtl black_abs',
    'Ka 0.02 0.02 0.02',
    'Kd 0.015 0.015 0.015',
    'Ks 0.04 0.04 0.04',
    '',
    'newmtl black_hole',
    'Ka 0.0 0.0 0.0',
    'Kd 0.0 0.0 0.0',
    '',
    'newmtl blue_lcd',
    'Ka 0.0 0.12 0.55',
    'Kd 0.0 0.22 0.85',
    'Ks 0.25 0.40 0.80',
    '',
    'newmtl clear_acrylic',
    'Ka 0.70 0.90 1.0',
    'Kd 0.70 0.90 1.0',
    'd 0.32',
    'Tr 0.68',
    '',
    'newmtl acrylic_highlight',
    'Ka 0.95 0.98 1.0',
    'Kd 0.85 0.93 1.0',
    'd 0.55',
    '',
    'newmtl sticker_white',
    'Ka 1.0 1.0 1.0',
    'Kd 1.0 1.0 1.0',
    '',
    'newmtl sticker_blue',
    'Ka 0.0 0.18 0.65',
    'Kd 0.0 0.27 0.82',
    '',
    'newmtl metal_dark',
    'Ka 0.12 0.12 0.12',
    'Kd 0.22 0.22 0.22',
    'Ks 0.40 0.40 0.40',
    '',
    'newmtl metal_light',
    'Ka 0.55 0.55 0.55',
    'Kd 0.72 0.72 0.72',
    'Ks 0.80 0.80 0.80',
    '',
    'newmtl case_shadow',
    'Ka 0.50 0.48 0.44',
    'Kd 0.50 0.48 0.44',
    '',
  ].join('\n');
}

function readmeFile() {
  return [
    '# SLSU BC Patrol Tinkercad Device Model',
    '',
    'This folder contains a Tinkercad-ready 3D reference model based on the provided technical device design.',
    '',
    '## Modeled Measurements',
    '',
    '- Enclosure width: 120 mm',
    '- Enclosure height: 200 mm',
    '- Enclosure depth: 75 mm',
    '- Front elements: black ABS LCD bezel, LCD, acrylic LCD cover, scan sticker, four screws',
    '- Side element: buzzer',
    '- Top element: DC barrel jack power input',
    '',
    '## Files',
    '',
    '- `slsu-bc-patrol-device-model.obj` - import this into Tinkercad.',
    '- `slsu-bc-patrol-device-model.mtl` - material/color reference for apps that read OBJ materials.',
    '- `slsu-bc-patrol-device-model.stl` - fallback import file if OBJ upload is not accepted.',
    '- `slsu-bc-patrol-device-model.zip` - packaged copy of the OBJ, MTL, STL, and this README.',
    '- `generate-device-model.cjs` - generator used to rebuild the model.',
    '',
    '## Tinkercad Import',
    '',
    '1. Open the Tinkercad design.',
    '2. Click **Import**.',
    '3. Choose `slsu-bc-patrol-device-model.obj`. If that does not upload, choose `slsu-bc-patrol-device-model.stl`.',
    '4. Keep the unit scale in millimeters.',
    '5. The file is exported upright for Tinkercad, using X as width, Y as depth, and Z as height.',
    '6. If colors are not preserved by Tinkercad, recolor the imported shape manually. Tinkercad often ignores OBJ/MTL material colors.',
    '',
    'Note: The model is intended as a scaled visual replica for design presentation and layout planning. It is not yet a printable mechanical enclosure with internal mounts or screw bosses.',
    '',
  ].join('\n');
}

function crc32(buffer) {
  const table = crc32.table || (crc32.table = Array.from({ length: 256 }, (_, n) => {
    let c = n;
    for (let k = 0; k < 8; k++) {
      c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
    }
    return c >>> 0;
  }));
  let crc = 0xffffffff;
  for (const byte of buffer) {
    crc = table[(crc ^ byte) & 0xff] ^ (crc >>> 8);
  }
  return (crc ^ 0xffffffff) >>> 0;
}

function dosDateTime(date) {
  const time = ((date.getHours() & 0x1f) << 11) | ((date.getMinutes() & 0x3f) << 5) | ((Math.floor(date.getSeconds() / 2)) & 0x1f);
  const day = date.getDate();
  const month = date.getMonth() + 1;
  const year = Math.max(date.getFullYear(), 1980) - 1980;
  const dosDate = ((year & 0x7f) << 9) | ((month & 0x0f) << 5) | (day & 0x1f);
  return { time, date: dosDate };
}

function makeZip(filesToZip, destination) {
  const localParts = [];
  const centralParts = [];
  let offset = 0;
  const now = new Date();
  const stamp = dosDateTime(now);

  for (const file of filesToZip) {
    const name = Buffer.from(file.name.replace(/\\/g, '/'));
    const data = fs.readFileSync(file.path);
    const compressed = zlib.deflateRawSync(data);
    const crc = crc32(data);

    const local = Buffer.alloc(30);
    local.writeUInt32LE(0x04034b50, 0);
    local.writeUInt16LE(20, 4);
    local.writeUInt16LE(0, 6);
    local.writeUInt16LE(8, 8);
    local.writeUInt16LE(stamp.time, 10);
    local.writeUInt16LE(stamp.date, 12);
    local.writeUInt32LE(crc, 14);
    local.writeUInt32LE(compressed.length, 18);
    local.writeUInt32LE(data.length, 22);
    local.writeUInt16LE(name.length, 26);
    local.writeUInt16LE(0, 28);

    localParts.push(local, name, compressed);

    const central = Buffer.alloc(46);
    central.writeUInt32LE(0x02014b50, 0);
    central.writeUInt16LE(20, 4);
    central.writeUInt16LE(20, 6);
    central.writeUInt16LE(0, 8);
    central.writeUInt16LE(8, 10);
    central.writeUInt16LE(stamp.time, 12);
    central.writeUInt16LE(stamp.date, 14);
    central.writeUInt32LE(crc, 16);
    central.writeUInt32LE(compressed.length, 20);
    central.writeUInt32LE(data.length, 24);
    central.writeUInt16LE(name.length, 28);
    central.writeUInt16LE(0, 30);
    central.writeUInt16LE(0, 32);
    central.writeUInt16LE(0, 34);
    central.writeUInt16LE(0, 36);
    central.writeUInt32LE(0, 38);
    central.writeUInt32LE(offset, 42);
    centralParts.push(central, name);

    offset += local.length + name.length + compressed.length;
  }

  const centralSize = centralParts.reduce((sum, part) => sum + part.length, 0);
  const end = Buffer.alloc(22);
  end.writeUInt32LE(0x06054b50, 0);
  end.writeUInt16LE(0, 4);
  end.writeUInt16LE(0, 6);
  end.writeUInt16LE(filesToZip.length, 8);
  end.writeUInt16LE(filesToZip.length, 10);
  end.writeUInt32LE(centralSize, 12);
  end.writeUInt32LE(offset, 16);
  end.writeUInt16LE(0, 20);

  fs.writeFileSync(destination, Buffer.concat([...localParts, ...centralParts, end]));
}

function vectorSub(a, b) {
  return [a[0] - b[0], a[1] - b[1], a[2] - b[2]];
}

function cross(a, b) {
  return [
    a[1] * b[2] - a[2] * b[1],
    a[2] * b[0] - a[0] * b[2],
    a[0] * b[1] - a[1] * b[0],
  ];
}

function normalize(n) {
  const length = Math.hypot(n[0], n[1], n[2]) || 1;
  return n.map((value) => round(value / length));
}

function toTinkercadPoint(point) {
  const [x, y, z] = point;
  return [x, z, y];
}

function stlFile() {
  const lines = ['solid slsu_bc_patrol_device'];
  for (const triangle of stlTriangles) {
    const pts = triangle.map((index) => toTinkercadPoint(vertices[index - 1]));
    const normal = normalize(cross(vectorSub(pts[1], pts[0]), vectorSub(pts[2], pts[0])));
    lines.push(`  facet normal ${normal[0]} ${normal[1]} ${normal[2]}`);
    lines.push('    outer loop');
    for (const [x, y, z] of pts) {
      lines.push(`      vertex ${x} ${y} ${z}`);
    }
    lines.push('    endloop');
    lines.push('  endfacet');
  }
  lines.push('endsolid slsu_bc_patrol_device');
  lines.push('');
  return lines.join('\n');
}

buildModel();

const obj = [
  '# SLSU BC Patrol RFID checkpoint device model',
  '# Units: millimeters',
  '# Tinkercad axes: X=width, Y=depth, Z=height',
  'mtllib slsu-bc-patrol-device-model.mtl',
  ...vertices.map((point) => {
    const [x, y, z] = toTinkercadPoint(point);
    return `v ${x} ${y} ${z}`;
  }),
  ...faces,
  '',
].join('\n');

fs.writeFileSync(objPath, obj);
fs.writeFileSync(mtlPath, materialFile());
fs.writeFileSync(stlPath, stlFile());
fs.writeFileSync(readmePath, readmeFile());
makeZip([
  { name: 'slsu-bc-patrol-device-model.obj', path: objPath },
  { name: 'slsu-bc-patrol-device-model.mtl', path: mtlPath },
  { name: 'slsu-bc-patrol-device-model.stl', path: stlPath },
  { name: 'README.md', path: readmePath },
], zipPath);

console.log(`Generated ${path.relative(process.cwd(), objPath)}`);
console.log(`Generated ${path.relative(process.cwd(), mtlPath)}`);
console.log(`Generated ${path.relative(process.cwd(), stlPath)}`);
console.log(`Generated ${path.relative(process.cwd(), readmePath)}`);
console.log(`Generated ${path.relative(process.cwd(), zipPath)}`);
console.log(`${vertices.length} vertices, ${faces.filter((line) => line.startsWith('f ')).length} faces`);
