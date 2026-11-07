const { test, expect } = require('@playwright/test');

// The places pages: every county and independent city, with its legislators and the bills that
// have named it. HJ14 (2024) names Richmond.

test('/places/ lists every county and city', async ({ page }) => {
  await page.goto('/places/');
  const lists = page.locator('ul.place-list');
  await expect(lists).toHaveCount(2);
  await expect(lists.nth(0).locator('li')).toHaveCount(95);
  await expect(lists.nth(1).locator('li')).toHaveCount(38);
  await expect(page.locator('a[href="/places/richmond/"]')).toHaveText('City of Richmond');
});

test('a place page lists its legislators and its bills by year', async ({ page }) => {
  await page.goto('/places/richmond/');
  await expect(page.locator('h1, h2').first()).toContainText('City of Richmond');
  await expect(page.locator('.place-intro')).toContainText('The City of Richmond is represented');

  // Three delegates, then two senators, in one list, and none of them Richmond County's.
  await expect(page.locator('ul.place-legislators')).toHaveCount(1);
  const chambers = await page.locator('ul.place-legislators li').evaluateAll(items => items.map(li => li.className));
  expect(chambers).toEqual(['house', 'house', 'house', 'senate', 'senate']);
  await expect(page.locator('ul.place-legislators li.house .place-legislator-name').first()).toContainText('Del.');
  await expect(page.locator('ul.place-legislators li.senate .place-legislator-name').first()).toContainText('Sen.');

  // Switching to the 2024 tab shows HJ14.
  await page.locator('#place-bills a[href="#bills-2024"]').click();
  await expect(page.locator('#bills-2024')).toBeVisible();
  await expect(page.locator('#bills-2024 a[href="/bill/2024/hj14/"]')).toBeVisible();
});

// The map needs a Mapbox token (settings.local.inc.php), so it's skipped where there isn't one.
test('a place page maps its districts, House in blue and Senate in red', async ({ page }) => {
  await page.goto('/places/richmond/');
  test.skip(await page.locator('#place-map').count() === 0, 'No Mapbox token is set');

  await page.waitForFunction(() => window.placeMap && window.placeMap.getLayer('place-outline'), null, { timeout: 30000 });
  const layers = await page.evaluate(() => ({
    house: window.placeMap.getPaintProperty('districts-house-fill', 'fill-color'),
    senate: window.placeMap.getPaintProperty('districts-senate-fill', 'fill-color'),
    opacity: window.placeMap.getPaintProperty('districts-house-fill', 'fill-opacity'),
    districts: window.placeMap.getSource('shapes')._data.features
      .filter(f => f.properties.kind === 'district').map(f => f.properties.label).sort(),
  }));
  expect(layers.house).toBe('#1f5fbf');
  expect(layers.senate).toBe('#c0392b');
  expect(layers.opacity).toBeLessThan(0.5);   // Translucent, so that the chambers can overlap
  expect(layers.districts).toEqual(['House 77', 'House 78', 'House 79', 'Senate 14', 'Senate 15']);
});

// The map has to be visible, not just present in the page's JavaScript.
test('the map is drawn at a usable size, with its legend styled', async ({ page }) => {
  await page.goto('/places/richmond/');
  test.skip(await page.locator('#place-map').count() === 0, 'No Mapbox token is set');

  await page.waitForFunction(() => window.placeMap && window.placeMap.loaded(), null, { timeout: 30000 });
  const canvas = page.locator('#place-map canvas.mapboxgl-canvas');
  await expect(canvas).toBeVisible();
  const box = await canvas.boundingBox();
  expect(box.width).toBeGreaterThan(400);
  expect(box.height).toBeGreaterThan(300);

  // The legend's swatches are colored squares, and its items aren't bulleted.
  const swatch = page.locator('.place-map-legend .swatch.house');
  await expect(swatch).toBeVisible();
  expect(await swatch.evaluate(el => getComputedStyle(el).backgroundColor)).not.toBe('rgba(0, 0, 0, 0)');
  expect(await page.locator('.place-map-legend li').first().evaluate(el => getComputedStyle(el).listStyleType)).toBe('none');
});

test('clicking the map lists the districts there, with links to their legislators', async ({ page }) => {
  await page.goto('/places/richmond/');
  test.skip(await page.locator('#place-map').count() === 0, 'No Mapbox token is set');

  await page.waitForFunction(() => window.placeMap && window.placeMap.loaded(), null, { timeout: 30000 });
  await page.locator('#place-map').click({ position: { x: 420, y: 200 } });
  const popup = page.locator('.place-map-popup');
  await expect(popup).toBeVisible();
  await expect(popup.locator('li')).toHaveCount(2);   // One House district and one Senate district
  await expect(popup.locator('a[href^="/legislator/"]').first()).toBeVisible();
});

test('clicking outside the place lists nothing', async ({ page }) => {
  await page.goto('/places/richmond/');
  test.skip(await page.locator('#place-map').count() === 0, 'No Mapbox token is set');

  await page.waitForFunction(() => window.placeMap && window.placeMap.loaded(), null, { timeout: 30000 });
  await page.locator('#place-map').click({ position: { x: 20, y: 20 } });   // A corner, outside the city
  await page.waitForTimeout(300);
  await expect(page.locator('.place-map-popup')).toHaveCount(0);
});

// Charlottesville is smaller than both of its districts. Its House label used to be hidden by its
// Senate label, and its districts were drawn as if they ended at the city line.
test('a small city says, and shows, that its districts extend beyond it', async ({ page }) => {
  await page.goto('/places/charlottesville/');
  await expect(page.locator('.place-districts-summary')).toHaveText(
    'All of Charlottesville is in House District 54 and Senate District 11. '
    + 'Both districts also include parts of neighboring localities.'
  );
  test.skip(await page.locator('#place-map').count() === 0, 'No Mapbox token is set');

  await page.waitForFunction(() => window.placeMap && window.placeMap.loaded()
    && window.placeMap.getLayer('districts-house-label'), null, { timeout: 30000 });
  await page.waitForTimeout(1000);   // Labels are placed just after the map loads
  const shown = await page.evaluate(() => [...new Set(window.placeMap
    .queryRenderedFeatures({ layers: ['districts-house-label', 'districts-senate-label'] })
    .map(f => f.properties.label))].sort());
  expect(shown).toEqual(['House 54', 'Senate 11']);

  // The districts are drawn outside the city, under a mask that fades them.
  expect(await page.evaluate(() => window.placeMap.getLayer('outside-mask') !== undefined)).toBe(true);
  const outside = await page.evaluate(() => window.placeMap
    .queryRenderedFeatures([15, 190], { layers: ['districts-senate-fill'] }).length);   // The map's west edge
  expect(outside).toBeGreaterThan(0);
});

test('a place page has a canonical URL and a description', async ({ page }) => {
  await page.goto('/places/richmond-county/');
  await expect(page.locator('link[rel="canonical"]')).toHaveAttribute(
    'href', 'https://www.richmondsunlight.com/places/richmond-county/'
  );
  await expect(page.locator('meta[name="description"]')).toHaveAttribute(
    'content', /Richmond County is represented/
  );
});

test('an unknown place is a 404', async ({ page }) => {
  const response = await page.goto('/places/nowhere/');
  expect(response.status()).toBe(404);
});

test('place URLs without a trailing slash redirect to ones with it', async ({ page }) => {
  for (const [from, to] of [
    ['/places', '/places/'],
    ['/places/richmond', '/places/richmond/'],
    ['/places/richmond-county', '/places/richmond-county/'],
    ['/places/Richmond', '/places/richmond/'],   // Lowercased, in the same redirect
  ]) {
    const response = await page.request.get(from, { maxRedirects: 0 });
    expect(response.status(), from).toBe(301);
    expect(response.headers()['location'], from).toMatch(new RegExp(to.replace(/\//g, '\\/') + '$'));
  }
});

test('old place URLs redirect to the new ones', async ({ page }) => {
  const response = await page.request.get('/places/fairfax+county/', { maxRedirects: 0 });
  expect(response.status()).toBe(301);
  expect(response.headers()['location']).toMatch(/\/places\/fairfax-county\/$/);
});

test('a bill page links the places it names', async ({ page }) => {
  await page.goto('/bill/2024/hj14/');
  await expect(page.locator('a[href="/places/richmond/"]')).toBeVisible();
});
