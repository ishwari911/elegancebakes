const cp = require('child_process');
const fs = require('fs');
try {
    cp.execSync('C:\\xampp\\mysql\\bin\\mysqldump.exe -u root elegance_bakes > elegance_bakes_export.sql');
} catch (e) {
    console.log(e);
}
let lines = fs.readFileSync('elegance_bakes_export.sql', 'utf8').split('\n');
let out = [];
for (let i = 0; i < lines.length; i++) {
    let l = lines[i];
    if (l.includes('CONSTRAINT') && l.includes('FOREIGN KEY')) {
        continue;
    }
    if (l.includes('KEY `cake_id` (`cake_id`),')) {
        l = l.replace(',', '');
    }
    out.push(l);
}
fs.writeFileSync('elegance_bakes_export.sql', out.join('\n'));
console.log('Done!');
