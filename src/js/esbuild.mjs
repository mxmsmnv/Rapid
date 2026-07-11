/**
 * esbuild config for Rapid
 *
 * Produces a single IIFE bundle: dist/editor.js
 * All Editor.js tools are included — no separate requests.
 *
 * Usage:
 *   npm run build   # minified production bundle
 *   npm run dev     # unminified + sourcemap
 */

import esbuild from 'esbuild';
import fs from 'node:fs';
import path from 'node:path';
import { createHash } from 'node:crypto';

const isDev  = process.argv.includes('--dev');
const outdir = path.join(import.meta.dirname, '../../assets/js/dist');

fs.mkdirSync(outdir, { recursive: true });

const result = await esbuild.build({
	entryPoints: [path.join(import.meta.dirname, 'editor.js')],
	bundle:      true,
	minify:      !isDev,
	sourcemap:   isDev ? 'inline' : false,
	format:      'iife',
	target:      ['es2017', 'chrome80', 'firefox78', 'safari13'],
	outdir,
	metafile:    true,
	logLevel:    'info',
});

// Write a version stamp so InputfieldRapid can bust cache
const outFile = path.join(outdir, 'editor.js');
const hash    = createHash('md5').update(fs.readFileSync(outFile)).digest('hex').slice(0, 8);
fs.writeFileSync(path.join(outdir, 'version.txt'), hash);

console.log(`\n✓ dist/editor.js  [${hash}]`);
