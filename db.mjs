import { DatabaseSync } from 'node:sqlite';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

const directory = resolve(process.env.TORTA_DATA || 'data');
mkdirSync(directory, { recursive: true });
export const db = new DatabaseSync(resolve(directory, 'torta.sqlite'));
db.exec(`
 PRAGMA foreign_keys=ON;
 PRAGMA journal_mode=WAL;
 PRAGMA busy_timeout=5000;
 CREATE TABLE IF NOT EXISTS users(id INTEGER PRIMARY KEY,name TEXT NOT NULL,email TEXT NOT NULL UNIQUE,password TEXT NOT NULL,role TEXT NOT NULL DEFAULT 'customer',active INTEGER NOT NULL DEFAULT 1,photo BLOB);
 CREATE TABLE IF NOT EXISTS sessions(token TEXT PRIMARY KEY,user INTEGER REFERENCES users,csrf TEXT NOT NULL,expires INTEGER NOT NULL);
 CREATE TABLE IF NOT EXISTS categories(id INTEGER PRIMARY KEY,name TEXT NOT NULL UNIQUE,active INTEGER NOT NULL DEFAULT 1);
 CREATE TABLE IF NOT EXISTS products(id INTEGER PRIMARY KEY,name TEXT NOT NULL,category INTEGER NOT NULL REFERENCES categories,price INTEGER NOT NULL CHECK(price>0),stock INTEGER NOT NULL CHECK(stock>=0),image TEXT,photo BLOB,active INTEGER NOT NULL DEFAULT 1);
 CREATE TABLE IF NOT EXISTS sales(id INTEGER PRIMARY KEY,user INTEGER NOT NULL REFERENCES users,product TEXT NOT NULL,pid INTEGER NOT NULL REFERENCES products,qty INTEGER NOT NULL,total INTEGER NOT NULL,status TEXT NOT NULL,method TEXT,created TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,paid TEXT);
 CREATE TABLE IF NOT EXISTS purchases(id INTEGER PRIMARY KEY,supplier TEXT NOT NULL,pid INTEGER NOT NULL REFERENCES products,product TEXT NOT NULL,qty INTEGER NOT NULL,total INTEGER NOT NULL,status TEXT NOT NULL,created TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
 CREATE TABLE IF NOT EXISTS audit(id INTEGER PRIMARY KEY,created TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,actor INTEGER,event TEXT NOT NULL,record INTEGER);
 CREATE TABLE IF NOT EXISTS attempts(key TEXT PRIMARY KEY,count INTEGER NOT NULL,expires INTEGER NOT NULL);
`);
export const run = (sql,...args) => db.prepare(sql).run(...args);
export const one = (sql,...args) => db.prepare(sql).get(...args);
export const all = (sql,...args) => db.prepare(sql).all(...args);
export function transaction(fn) {
  db.exec('BEGIN IMMEDIATE');
  try { const result = fn(); db.exec('COMMIT'); return result; }
  catch (error) { db.exec('ROLLBACK'); throw error; }
}
transaction(() => {
  if (one('SELECT id FROM categories LIMIT 1')) return;
  for (const name of ['Cokelat','Buah','Keju']) run('INSERT INTO categories(name) VALUES(?)',name);
  for (const row of [['Chocolate Ganache',1,165000,12,'cokelat.jpg'],['Strawberry Shortcake',2,185000,8,'stroberi.jpg'],['Classic Cheesecake',3,175000,10,'keju.jpg']])
    run('INSERT INTO products(name,category,price,stock,image) VALUES(?,?,?,?,?)',...row);
});
