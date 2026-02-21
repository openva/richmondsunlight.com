-- Test user fixtures for local/Docker environments
USE richmondsunlight;

-- Clean up poll votes for the test bill so the unique constraint doesn't block re-runs
DELETE FROM polls WHERE bill_id = 76483;

-- Remove any previous copies of these test users
DELETE FROM dashboard_bills WHERE user_id IN (90001, 90002, 90003, 90004);
DELETE FROM dashboard_user_data WHERE user_id IN (90001, 90002, 90003, 90004);
DELETE FROM dashboard_portfolios WHERE user_id IN (90001, 90002, 90003, 90004);

DELETE FROM users
WHERE id IN (90001, 90002, 90003, 90004)
   OR cookie_hash IN (
        'asdfghjkasdfghjkasdfghjk00000001',
        'asdfghjkasdfghjkasdfghjk00000002',
        'asdfghjkasdfghjkasdfghjk00000003',
        'asdfghjkasdfghjkasdfghjk00000004'
    );

-- Untrusted user
INSERT INTO users (
    id, cookie_hash, name, password, email, url, zip, city, state,
    house_district_id, senate_district_id, representative_id,
    trusted, mailing_list, ip, notes, private_hash,
    latitude, longitude, date_created
) VALUES (
    90001,
    'asdfghjkasdfghjkasdfghjk00000001',
    'Test User',
    MD5('password123'),
    'testuser@example.com',
    'https://example.com',
    '23220',
    'Richmond',
    'VA',
    NULL,
    NULL,
    NULL,
    'n',
    'n',
    '127.0.0.1',
    'Untrusted test account',
    'testuser',
    NULL,
    NULL,
    NOW()
);

-- Trusted moderator user
INSERT INTO users (
    id, cookie_hash, name, password, email, url, zip, city, state,
    house_district_id, senate_district_id, representative_id,
    trusted, mailing_list, ip, notes, private_hash,
    latitude, longitude, date_created
) VALUES (
    90002,
    'asdfghjkasdfghjkasdfghjk00000002',
    'Trusted Tester',
    MD5('password123'),
    'trusted@example.com',
    'https://example.com/trusted',
    '23221',
    'Richmond',
    'VA',
    NULL,
    NULL,
    NULL,
    'y',
    'n',
    '127.0.0.1',
    'Trusted test account for moderation flows',
    'trustee1',
    NULL,
    NULL,
    NOW()
);

-- Legislator-linked user
INSERT INTO users (
    id, cookie_hash, name, password, email, url, zip, city, state,
    house_district_id, senate_district_id, representative_id,
    trusted, mailing_list, ip, notes, private_hash,
    latitude, longitude, date_created
) VALUES (
    90003,
    'asdfghjkasdfghjkasdfghjk00000003',
    'Legislator Tester',
    MD5('password123'),
    'legislator@example.com',
    'https://example.com/legislator',
    '22902',
    'Charlottesville',
    'VA',
    NULL,
    NULL,
    269,
    'y',
    'n',
    '127.0.0.1',
    'Linked to legislator id 269 for testing',
    'legis001',
    NULL,
    NULL,
    NOW()
);

-- Generic tester user for login tests
INSERT INTO users (
    id, cookie_hash, name, password, email, url, zip, city, state,
    house_district_id, senate_district_id, representative_id,
    trusted, mailing_list, ip, notes, private_hash,
    latitude, longitude, date_created
) VALUES (
    90004,
    'asdfghjkasdfghjkasdfghjk00000004',
    'Tester',
    MD5('password123'),
    'tester@example.com',
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    'n',
    'n',
    '127.0.0.1',
    'Generic test account for login tests',
    'tester01',
    NULL,
    NULL,
    NOW()
);

-- Portfolio to mark the basic test user as registered
INSERT INTO dashboard_portfolios (
    user_id, name, hash, notes, notify, public, watch_list_id, view_count, date_created
) VALUES (
    90001,
    'Test Portfolio',
    'pwt01',
    'Portfolio for test user',
    'none',
    'n',
    NULL,
    0,
    NOW()
);

-- Portfolio for tester@example.com
INSERT INTO dashboard_portfolios (
    user_id, name, hash, notes, notify, public, watch_list_id, view_count, date_created
) VALUES (
    90004,
    'Tester Portfolio',
    'pwt04',
    'Portfolio for tester@example.com',
    'none',
    'n',
    NULL,
    0,
    NOW()
);

-- Public portfolio for testuser (for public portfolio and portfolio listing tests)
INSERT INTO dashboard_portfolios (
    user_id, name, hash, notes, notify, public, watch_list_id, view_count, date_created
) VALUES (
    90001,
    'Public Test Portfolio',
    'pwtp1',
    'Public portfolio for browser testing',
    'none',
    'y',
    NULL,
    0,
    NOW()
);

-- Dashboard user data: gives test users access to Photosynthesis
INSERT INTO dashboard_user_data (user_id, type, organization, email_active, last_access, unsub_hash, date_created)
VALUES (90001, 'paid', 'Test Organization', 'y', NOW(), 'pwtest01', NOW());

INSERT INTO dashboard_user_data (user_id, type, organization, email_active, last_access, unsub_hash, date_created)
VALUES (90004, 'free', NULL, 'y', NOW(), 'pwtest04', NOW());

-- Pre-load HB2049 (bill ID 77600, 2025 session) into both portfolios for view/public tests
INSERT INTO dashboard_bills (user_id, bill_id, portfolio_id, date_created)
VALUES (90001, 77600, (SELECT id FROM dashboard_portfolios WHERE hash='pwt01'), NOW());

INSERT INTO dashboard_bills (user_id, bill_id, portfolio_id, date_created)
VALUES (90001, 77600, (SELECT id FROM dashboard_portfolios WHERE hash='pwtp1'), NOW());
