const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const zlib = require('zlib');

const repoRoot = path.resolve(__dirname, '..', '..');
const tinkercadGenerator = path.join(repoRoot, 'hardware', 'tinkercad', 'generate-device-model.cjs');
const objPath = path.join(repoRoot, 'hardware', 'tinkercad', 'slsu-bc-patrol-device-model.obj');
const outDir = __dirname;
const modelPath = path.join(outDir, 'slsu-bc-patrol-onshape-model.3mf');
const readmePath = path.join(outDir, 'README.md');

const colors = {
  white_plastic: '#F2F0E8FF',
  black_abs: '#111111FF',
  black_hole: '#000000FF',
  blue_lcd: '#0057D9FF',
  clear_acrylic: '#B8E6FF88',
  acrylic_highlight: '#E6F8FFFF',
  sticker_white: '#FFFFFFFF',
  sticker_blue: '#0057D9FF',
  metal_dark: '#333333FF',
  metal_light: '#B8B8B8FF',
  case_shadow: '#77736AFF',
};

function escapeXml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

function parseObj(file) {
  const vertices = [];
  const materialMeshes = new Map();
  let currentMaterial = 'white_plastic';

  function meshFor(material) {
    if (!materialMeshes.has(material)) {
      materialMeshes.set(material, {
        vertices: [],
        vertexMap: new Map(),
        triangles: [],
      });
    }
    return materialMeshes.get(material);
  }

  function localIndex(mesh, index) {
    if (!mesh.vertexMap.has(index)) {
      mesh.vertexMap.set(index, mesh.vertices.length);
      mesh.vertices.push(vertices[index - 1]);
    }
    return mesh.vertexMap.get(index);
  }

  for (const line of fs.readFileSync(file, 'utf8').split(/\r?\n/)) {
    if (line.startsWith('v ')) {
      vertices.push(line.trim().split(/\s+/).slice(1).map(Number));
      continue;
    }

    if (line.startsWith('usemtl ')) {
      currentMaterial = line.trim().split(/\s+/)[1] || currentMaterial;
      continue;
    }

    if (!line.startsWith('f ')) {
      continue;
    }

    const mesh = meshFor(currentMaterial);
    const face = line.trim().split(/\s+/).slice(1).map((token) => Number(token.split('/')[0]));
    if (face.length === 3) {
      mesh.triangles.push(face.map((index) => localIndex(mesh, index)));
    } else if (face.length === 4) {
      mesh.triangles.push([face[0], face[1], face[2]].map((index) => localIndex(mesh, index)));
      mesh.triangles.push([face[0], face[2], face[3]].map((index) => localIndex(mesh, index)));
    }
  }

  return Array.from(materialMeshes.entries())
    .filter(([, mesh]) => mesh.triangles.length > 0)
    .map(([material, mesh]) => ({ material, ...mesh }));
}

function modelXml(meshes) {
  const materialNames = Array.from(new Set(meshes.map((mesh) => mesh.material)));
  const materialIndex = new Map(materialNames.map((name, index) => [name, index]));
  const objectIdStart = 2;

  const materialXml = materialNames.map((name) => {
    const color = colors[name] || '#CCCCCCFF';
    return `      <m:base name="${escapeXml(name)}" displaycolor="${color}"/>`;
  }).join('\n');

  const objectXml = meshes.map((mesh, index) => {
    const objectId = objectIdStart + index;
    const pindex = materialIndex.get(mesh.material);
    const vertices = mesh.vertices.map(([x, y, z]) =>
      `          <vertex x="${x}" y="${y}" z="${z}"/>`
    ).join('\n');
    const triangles = mesh.triangles.map(([a, b, c]) =>
      `          <triangle v1="${a}" v2="${b}" v3="${c}"/>`
    ).join('\n');

    return [
      `    <object id="${objectId}" type="model" name="${escapeXml(mesh.material)}" pid="1" pindex="${pindex}">`,
      '      <mesh>',
      '        <vertices>',
      vertices,
      '        </vertices>',
      '        <triangles>',
      triangles,
      '        </triangles>',
      '      </mesh>',
      '    </object>',
    ].join('\n');
  }).join('\n');

  const buildXml = meshes.map((_, index) =>
    `    <item objectid="${objectIdStart + index}"/>`
  ).join('\n');

  return [
    '<?xml version="1.0" encoding="UTF-8"?>',
    '<model unit="millimeter" xml:lang="en-US" xmlns="http://schemas.microsoft.com/3dmanufacturing/core/2015/02" xmlns:m="http://schemas.microsoft.com/3dmanufacturing/material/2015/02">',
    '  <metadata name="Title">SLSU BC Patrol RFID Device</metadata>',
    '  <metadata name="Designer">SLSU BC Patrol Capstone Team</metadata>',
    '  <resources>',
    '    <m:basematerials id="1">',
    materialXml,
    '    </m:basematerials>',
    objectXml,
    '  </resources>',
    '  <build>',
    buildXml,
    '  </build>',
    '</model>',
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
  const stamp = dosDateTime(new Date());

  for (const file of filesToZip) {
    const name = Buffer.from(file.name.replace(/\\/g, '/'));
    const data = Buffer.isBuffer(file.data) ? file.data : Buffer.from(file.data);
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

function readmeFile(meshes) {
  return [
    '# SLSU BC Patrol Onshape Device Model',
    '',
    'This folder contains an Onshape-ready 3MF model based on the hand-drawn technical device design.',
    '',
    '## Main Measurements',
    '',
    '- Enclosure body: 120 mm width x 200 mm height x 75 mm depth',
    '- Overall imported size is slightly larger because the side buzzer, front acrylic/LCD cover, screws, and top barrel jack protrude from the enclosure.',
    '',
    '## Included Details',
    '',
    '- White plastic enclosure',
    '- Black ABS LCD bezel/front panel',
    '- Blue LCD display with acrylic cover',
    '- Lower scan sticker with RFID/card icon and SCAN HERE text approximation',
    '- Four front screws',
    '- Side buzzer',
    '- Top DC barrel jack power input',
    '',
    '## Onshape Import',
    '',
    '1. Open or create an Onshape document.',
    '2. Click the **+** button at the bottom-left tab area.',
    '3. Choose **Import**.',
    '4. Select `slsu-bc-patrol-onshape-model.3mf`.',
    '5. Keep the unit scale in millimeters.',
    '',
    'The 3MF file separates the model by material/color groups so it should look closer to the hand-drawn design than the single-color OBJ import.',
    '',
    '## Material Groups',
    '',
    ...meshes.map((mesh) => `- ${mesh.material}: ${mesh.triangles.length} triangles`),
    '',
  ].join('\n');
}

const contentTypes = [
  '<?xml version="1.0" encoding="UTF-8"?>',
  '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">',
  '  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>',
  '  <Default Extension="model" ContentType="application/vnd.ms-package.3dmanufacturing-3dmodel+xml"/>',
  '</Types>',
  '',
].join('\n');

const relationships = [
  '<?xml version="1.0" encoding="UTF-8"?>',
  '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">',
  '  <Relationship Target="/3D/3dmodel.model" Id="rel0" Type="http://schemas.microsoft.com/3dmanufacturing/2013/01/3dmodel"/>',
  '</Relationships>',
  '',
].join('\n');

execFileSync(process.execPath, [tinkercadGenerator], { cwd: repoRoot, stdio: 'ignore' });

const meshes = parseObj(objPath);
makeZip([
  { name: '[Content_Types].xml', data: contentTypes },
  { name: '_rels/.rels', data: relationships },
  { name: '3D/3dmodel.model', data: modelXml(meshes) },
], modelPath);
fs.writeFileSync(readmePath, readmeFile(meshes));

console.log(`Generated ${path.relative(repoRoot, modelPath)}`);
console.log(`Generated ${path.relative(repoRoot, readmePath)}`);
console.log(`${meshes.length} material groups`);
