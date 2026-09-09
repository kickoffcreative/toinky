-- Remove default-managed page body sections while preserving standard headers.
-- HERO is the standard header on every page: the Home HERO is the parallax
-- hero, while other pages use the shared non-front-page header renderer.
-- Non-default sections are preserved for writer-authored content.
DELETE s
FROM site_page_sections s
WHERE s.is_default=1
  AND s.section_code <> 'HERO';
