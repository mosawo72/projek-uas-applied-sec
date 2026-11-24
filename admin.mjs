import { one, run, transaction } from './db.mjs';
const email = process.argv[2]?.trim().toLowerCase();
const user = email && one('SELECT id FROM users WHERE email=? AND active=1',email);
if (!user) {
  console.error('Daftarkan akun di aplikasi, lalu jalankan npm run admin -- email@anda.id dari folder proyek.');
  process.exitCode = 1;
} else {
  transaction(() => {
    run("UPDATE users SET role='administrator' WHERE id=?",user.id);
    run('DELETE FROM sessions WHERE user=?',user.id);
    run("INSERT INTO audit(event,record) VALUES('admin-cli',?)",user.id);
  });
  console.log('Hak administrator diberikan. Silakan masuk kembali.');
}
