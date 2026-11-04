#!/usr/bin/env python3
"""Build a deterministic WordPress upload ZIP from the reviewed runtime allowlist."""
from pathlib import Path
import hashlib, json, re, subprocess, zipfile, os

root = Path(__file__).resolve().parents[1]
config = json.loads((root / 'wordpress-package.json').read_text(encoding='utf-8'))
plugin = root / config['root']
def source_bytes(relative):
    # Apply the same clean filters used by Git commits (including CRLF handling).
    # Include intentional working-tree edits without changing the Git index.
    try:
        digest = subprocess.check_output(['git','hash-object','-w','--path=' + relative,'--stdin'], input=(root / relative).read_bytes(), cwd=root, stderr=subprocess.PIPE).strip().decode('ascii')
        return subprocess.check_output(['git','cat-file','blob',digest], cwd=root, stderr=subprocess.PIPE)
    except subprocess.CalledProcessError: raise SystemExit('Cannot read canonical source bytes: ' + relative)
header = source_bytes((plugin / config['entry']).relative_to(root).as_posix()).decode('utf-8')
def field(name, text):
    found = re.search(r'^\s*(?:\*\s*)?' + re.escape(name) + r':\s*(.+?)\s*$', text, re.M)
    if not found: raise SystemExit('Missing metadata: ' + name)
    return found.group(1)
version = field('Version', header)
constant_pattern = r"define\(\s*['\"]" + re.escape(config['version_constant']) + r"['\"]\s*,\s*['\"]" + re.escape(version) + r"['\"]\s*\)"
if not re.search(constant_pattern, header): raise SystemExit('Runtime version constant and header differ')
readme = source_bytes((plugin / 'readme.txt').relative_to(root).as_posix()).decode('utf-8')
if field('Stable tag', readme) != version: raise SystemExit('Stable tag must match the plugin header')
if field('Text Domain', header) != config['slug']: raise SystemExit('Upload directory and text domain must match')
for key in ('Requires at least', 'Requires PHP', 'License', 'License URI'):
    if field(key, header) != field(key, readme): raise SystemExit('Header/readme mismatch: ' + key)
for required in (config['entry'], 'readme.txt', 'LICENSE', 'COPYRIGHT.txt'):
    if not (plugin / required).is_file(): raise SystemExit('Missing upload file: ' + required)
tracked = subprocess.check_output(['git','ls-files','--cached','--others','--exclude-standard'], cwd=root, text=True).splitlines()
files = {}
for name in sorted(set(tracked)):
    path = root / name
    try: relative = path.relative_to(plugin).as_posix()
    except ValueError: continue
    if not any(relative == item or relative.startswith(item + '/') for item in config['include']): continue
    if path.is_symlink(): raise SystemExit('Symlinks are not allowed: ' + name)
    if not path.is_file(): continue # Respect working-tree deletions; required files are checked below.
    parts = Path(relative).parts
    if parts[0] == 'vendor' or any(part in ('node_modules','tests','.git','.github','__pycache__') for part in parts):
        raise SystemExit('Development content in runtime allowlist: ' + relative)
    if relative.endswith(('~','.map','.log','.zip','.tgz','.bak')) or (Path(relative).name.startswith('.') and Path(relative).name != '.htaccess'):
        continue
    files[relative] = source_bytes(name)
if config['entry'] not in files: raise SystemExit('Entrypoint absent from tracked package inputs')
for required in config.get('required', []):
    if required not in files: raise SystemExit('Required runtime file absent from package inputs: ' + required)
out = root / 'release' / (config['slug'] + '-' + version + '.zip')
out.parent.mkdir(exist_ok=True)
# Stored entries are identical across Python/zlib versions on Windows and Linux.
with zipfile.ZipFile(out, 'w', compression=zipfile.ZIP_STORED) as archive:
    for name, data in sorted(files.items()):
        info = zipfile.ZipInfo(config['slug'] + '/' + name, (1980,1,1,0,0,0))
        info.create_system = 3
        info.external_attr = 0o100644 << 16
        info.compress_type = zipfile.ZIP_STORED
        archive.writestr(info, data)
with zipfile.ZipFile(out) as archive:
    if archive.testzip() is not None: raise SystemExit('ZIP CRC check failed')
    for name, data in files.items():
        if archive.read(config['slug'] + '/' + name) != data: raise SystemExit('ZIP/source mismatch: ' + name)
if out.stat().st_size >= 10 * 1024 * 1024: raise SystemExit('Upload ZIP exceeds the WordPress.org10MiB limit')
digest = hashlib.sha256(out.read_bytes()).hexdigest()
out.with_suffix('.zip.sha256').write_text(digest + '  ' + out.name + '\n', encoding='utf-8')
manifest = {'slug':config['slug'], 'version':version, 'archive':out.name, 'sha256':digest,
            'files':[{'path':name,'bytes':len(data),'sha256':hashlib.sha256(data).hexdigest()} for name,data in sorted(files.items())]}
out.with_suffix('.manifest.json').write_text(json.dumps(manifest,indent=2) + '\n',encoding='utf-8')
(out.parent / 'wordpress-upload.json').write_text(json.dumps(manifest,indent=2) + '\n',encoding='utf-8')
if os.environ.get('GITHUB_OUTPUT'):
    with open(os.environ['GITHUB_OUTPUT'], 'a', encoding='utf-8') as output:
        for key, path in [('archive',out),('checksum',out.with_suffix('.zip.sha256')),('manifest',out.with_suffix('.manifest.json'))]:
            output.write(key + '=' + path.relative_to(root).as_posix() + '\n')
print(str(out) + ' (' + str(len(files)) + ' files, SHA256 ' + digest + ')')
