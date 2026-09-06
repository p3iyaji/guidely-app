import { test, expect } from '@playwright/test';
import { writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const email = 'e2e.admin@example.sch.uk';
const password = 'password';
const schoolName = 'E2E Primary';

test.describe('Epic 2 import UJ-4 smoke', () => {
    test('admin imports a Pupil CSV and sees them on Pupils', async ({ page }) => {
        const csvPath = join(tmpdir(), `guidely-e2e-import-${Date.now()}.csv`);
        writeFileSync(
            csvPath,
            [
                'pupil_identifier,given_name,family_name,date_of_birth,school_name,school_id,year_group,sen_status,notes',
                `MIS-E2E-1,Jamie,Brooks,,${schoolName},,Year 7,sen_support,`,
                '',
            ].join('\n'),
        );

        await page.goto('/login');
        await page.locator('#email').fill(email);
        await page.locator('#password').fill(password);
        await page.getByRole('button', { name: 'Sign in' }).click();
        await expect(page.getByTestId('app-shell')).toBeVisible();

        await page.getByRole('link', { name: 'Import' }).click();
        await expect(page.getByTestId('import-page')).toBeVisible();

        await page.getByTestId('import-file-input').setInputFiles(csvPath);
        await page.getByTestId('import-upload-submit').click();

        await expect(page.getByTestId('import-results')).toBeVisible();
        await expect(page.getByTestId('import-results-summary')).toContainText('Committed 1');
        await expect(page.getByTestId('import-results-summary')).toContainText('errors 0');

        await page.getByRole('link', { name: 'Pupils' }).click();
        await expect(page.getByTestId('pupils-page')).toBeVisible();
        await expect(page.getByText('Jamie Brooks')).toBeVisible();
    });
});
