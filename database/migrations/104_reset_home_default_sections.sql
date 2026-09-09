-- Reset the Home page body to the intentional foundation.
-- Preserve the parallax hero and live Story Worlds cards; page-specific
-- non-front-page headers are separate page records and are untouched.
DELETE s
FROM site_page_sections s
JOIN site_pages p ON p.site_page_id=s.site_page_id
WHERE p.page_code='HOME'
  AND s.section_code NOT IN ('HERO','WORLDS');
