import { test, expect } from '@playwright/test';
async function noOverflow(page) {
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy();
}
async function toggle(page, language) {
    if (await page.locator('html').getAttribute('lang') !== language) {
        await page.getByTestId('language-toggle').click();
    }
    await expect(page.locator('html')).toHaveAttribute('lang', language);
    await expect(page.locator('html')).toHaveAttribute('dir', language === 'ar' ? 'rtl' : 'ltr');
    await expect(page.getByTestId('language-toggle')).toBeEnabled();
}
for (const language of ['en', 'ar']) {
    test(`${language}: register, sign in by phone and code, enroll in two courses`, async ({ page }, testInfo) => {
        const errors=[]; page.on('pageerror',e=>errors.push(e.message));
        const phone='010'+String(Date.now()).slice(-8);
        const password='MobilePassword123!';
        await page.goto('/register');
        await page.locator('[name=name]').fill('Mobile learner '+phone);
        await page.locator('[name=phone]').fill(phone);
        await page.locator('[name=password]').fill(password);
        await toggle(page, 'ar');
        await expect(page.locator('[name=name]')).toHaveValue('Mobile learner '+phone);
        await expect(page.locator('[name=password]')).toHaveValue(password);
        if (language==='en') await toggle(page,'en');
        await noOverflow(page);
        await page.locator('[name=whatsapp_declared]').check();
        await page.locator('[name=password_confirmation]').fill(password);
        await page.locator('main form button[type=submit], main form button.primary').click();
        await expect(page).toHaveURL(/\/login$/);
        const code=(await page.locator('[role=status]').innerText()).match(/STU-\d+/)[0];
        await page.locator('[name=identifier]').fill(phone);
        await page.locator('[name=password]').fill(password);
        await page.locator('main form button.primary').click();
        await expect(page).toHaveURL(/\/dashboard$/);
        await noOverflow(page);
        await page.goto('/student/courses');
        for (const title of ['Programming fundamentals','Data structures']) {
            await page.locator('#course-search').fill(title);
            const card=page.locator('article').filter({has:page.getByRole('heading',{name:title,exact:true})});
            await expect(card).toHaveCount(1);
            await card.getByRole('link').click();
            await page.locator('[name=group_id]').selectOption({label:'Group A'});
            await page.locator('main form button.primary').click();
            await expect(page.locator('[role=status]')).toBeVisible();
            await noOverflow(page);
            await page.goto('/student/courses');
        }
        await page.goto('/dashboard');
        await expect(page.locator('article')).toHaveCount(2);
        await page.screenshot({path:testInfo.outputPath(`dashboard-${language}.png`),fullPage:true});
        await page.locator('form[action$="/logout"] button').click();
        await page.locator('[name=identifier]').fill(code.toLowerCase());
        await page.locator('[name=password]').fill(password);
        await page.locator('main form button.primary').click();
        await expect(page).toHaveURL(/\/dashboard$/);
        await page.goto('/notifications');
        await expect(page.locator('article')).toHaveCount(3);
        await page.locator('article button').first().click();
        await expect(page.locator('article button')).toHaveCount(2);
        await noOverflow(page);
        expect(errors).toEqual([]);
        await page.goto('/instructor/offerings');
        await expect(page.locator('body')).toContainText('403');
    });
}
test('instructor creates semester, course, offering and two groups', async ({page}, testInfo) => {
    await page.goto('/login');
    await page.locator('[name=identifier]').fill('01000000001');
    await page.locator('[name=password]').fill('BrowserTest123!');
    await page.locator('main form button.primary').click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/instructor/setup');
    const unique=Date.now().toString();
    const semester=page.locator('form[action$="/setup/semesters"]');
    await semester.locator('[name=name]').fill('Semester '+unique);
    await semester.locator('[name=starts_on]').fill('2026-10-01');
    await semester.locator('[name=ends_on]').fill('2027-01-31');
    await semester.locator('button.primary').click();
    const course=page.locator('form[action$="/setup/courses"]');
    await course.locator('[name=name]').fill('Course '+unique);
    await course.locator('[name=code]').fill('C'+unique);
    await course.locator('button.primary').click();
    await page.goto('/instructor/offerings');
    const form=page.locator('form[action$="/instructor/offerings"]');
    await form.locator('[name=semester_id]').selectOption({label:'Semester '+unique});
    await form.locator('[name=course_id]').selectOption({label:'Course '+unique});
    await form.locator('[name=title]').fill('Offering '+unique);
    await form.locator('[name=fee]').fill('1500.00');
    await form.locator('[name=uses_groups]').check();
    await form.locator('button.primary').click();
    for(const name of ['Group A','Group B']) {
        const group=page.locator('form[action$="/groups"]');
        await group.locator('[name=name]').fill(name);
        await group.locator('[name=capacity]').fill('25');
        await group.locator('button.primary').click();
    }
    await expect(page.getByText('Group A',{exact:true})).toBeVisible();
    await expect(page.getByText('Group B',{exact:true})).toBeVisible();
    await noOverflow(page);
    await toggle(page,'ar');
    await noOverflow(page);
    await page.screenshot({path:testInfo.outputPath('instructor-ar.png'),fullPage:true});
});
test('anonymous actions and CSRF are blocked', async ({page,request}) => {
    await page.goto('/student/courses'); await expect(page).toHaveURL(/\/login$/);
    const response=await request.post('/register',{form:{name:'No token'}});
    expect(response.status()).toBe(419);
});
