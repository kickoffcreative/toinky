-- How It Works finale: restore light text on the dark finale band.
-- The finale row's text color ended up black-on-black after its layout switch;
-- the Page Builder color picker could not be used to correct it. Set the
-- standard paper color, but only when the row is still in the broken state
-- (black or unset) so a manually chosen color is never overwritten.
UPDATE site_page_sections s
JOIN site_pages p ON p.site_page_id = s.site_page_id
SET s.rows_json = JSON_SET(s.rows_json, '$[0].text_color', '#F4EEE2')
WHERE p.page_code = 'HOW_IT_WORKS'
  AND s.label_text = 'How It Works Finale'
  AND JSON_UNQUOTE(JSON_EXTRACT(s.rows_json, '$[0].title_text')) = 'Ready for Your Story Night?'
  AND (
    JSON_EXTRACT(s.rows_json, '$[0].text_color') IS NULL
    OR JSON_UNQUOTE(JSON_EXTRACT(s.rows_json, '$[0].text_color')) IN ('#000000', '')
  );
