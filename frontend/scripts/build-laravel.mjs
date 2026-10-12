// يبني الواجهة في dist وينسخها نضيفة لـ backend/public (من غير ما يلمس ملفات Laravel نفسها).
// قبل كده البناءات القديمة كانت بتتراكم، والـ Service Worker كان بيحمّلها كلها (٤ ميجا) أول مرة.
import { execSync } from 'node:child_process'
import { existsSync, readdirSync, rmSync } from 'node:fs'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const dist = join(root, 'dist')
const target = join(root, '..', 'backend', 'public')
const laravelOwned = new Set(['index.php', '.htaccess', 'favicon.ico', 'robots.txt', 'storage', 'build', 'web.config'])
const win = process.platform === 'win32'

/** حذف مضمون: fs.rmSync بيفشل بصمت على ويندوز مع مسارات فيها عربي، فبنرجع لأمر النظام */
function remove(path) {
  rmSync(path, { recursive: true, force: true })
  if (existsSync(path)) {
    execSync(win ? `powershell -NoProfile -Command "Remove-Item -LiteralPath '${path}' -Recurse -Force"` : `rm -rf "${path}"`)
  }
  if (existsSync(path)) throw new Error(`مقدرناش نمسح ${path}`)
}

remove(dist)
execSync('npx vite build', { cwd: root, stdio: 'inherit' })

for (const name of readdirSync(target)) {
  if (!laravelOwned.has(name)) remove(join(target, name))
}
// النسخ بأمر النظام: fs.cpSync بيقع (crash) على ويندوز مع مسارات فيها عربي
execSync(
  win
    ? `powershell -NoProfile -Command "Copy-Item -Path '${join(dist, '*')}' -Destination '${target}' -Recurse -Force"`
    : `cp -R "${dist}/." "${target}/"`,
)

if (!existsSync(join(target, 'index.html')) || !existsSync(join(target, 'sw.js'))) {
  throw new Error('النسخ لـ backend/public مكملش')
}
console.log(`\nتم: الواجهة اتنسخت لـ backend/public (${readdirSync(join(target, 'assets')).length} ملف في assets)`)
