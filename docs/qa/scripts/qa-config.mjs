import path from 'node:path';
import { fileURLToPath } from 'node:url';

const SCRIPT_DIR = path.dirname(fileURLToPath(import.meta.url));

export const WEBAPP_ROOT = path.resolve(SCRIPT_DIR, '../../..');
export const REPO_ROOT = path.resolve(WEBAPP_ROOT, '..', '..');
export const BASE_URL = process.env.PAWHAVEN_QA_BASE_URL || 'http://127.0.0.1:8000';
export const CHROME_PATH = process.env.CHROME_PATH;

if (! CHROME_PATH) {
    throw new Error('Set CHROME_PATH to a local Chromium executable before running browser QA scripts.');
}

/** @returns {{ email: string, password: string }} */
export function qaCredentials(role) {
    const prefix = `PAWHAVEN_QA_${role.toUpperCase()}`;
    const email = process.env[`${prefix}_EMAIL`];
    const password = process.env[`${prefix}_PASSWORD`];

    if (! email || ! password) {
        throw new Error(`Set ${prefix}_EMAIL and ${prefix}_PASSWORD in the local process environment.`);
    }

    return { email, password };
}
