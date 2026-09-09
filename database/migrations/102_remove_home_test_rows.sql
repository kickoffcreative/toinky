-- Remove two Home page-builder test rows without touching other sections.
-- The editor stores row objects in site_page_sections.rows_json; remove only rows
-- whose editable title, eyebrow, or label is exactly the temporary test heading.
UPDATE site_page_sections s
JOIN site_pages p ON p.site_page_id=s.site_page_id
SET s.rows_json=JSON_REMOVE(
  s.rows_json,
  CASE
    WHEN JSON_SEARCH(s.rows_json,'one','What Is Toinky',NULL,'$[*].title_text') IS NOT NULL
      THEN REPLACE(JSON_UNQUOTE(JSON_SEARCH(s.rows_json,'one','What Is Toinky',NULL,'$[*].title_text')),'.title_text','')
    WHEN JSON_SEARCH(s.rows_json,'one','What Is Toinky',NULL,'$[*].eyebrow_text') IS NOT NULL
      THEN REPLACE(JSON_UNQUOTE(JSON_SEARCH(s.rows_json,'one','What Is Toinky',NULL,'$[*].eyebrow_text')),'.eyebrow_text','')
    WHEN JSON_SEARCH(s.rows_json,'one','What Is Toinky',NULL,'$[*].label_text') IS NOT NULL
      THEN REPLACE(JSON_UNQUOTE(JSON_SEARCH(s.rows_json,'one','What Is Toinky',NULL,'$[*].label_text')),'.label_text','')
  END
)
WHERE p.page_code='HOME'
  AND s.rows_json IS NOT NULL
  AND JSON_SEARCH(s.rows_json,'one','What Is Toinky',NULL,'$[*].title_text','$[*].eyebrow_text','$[*].label_text') IS NOT NULL;

UPDATE site_page_sections s
JOIN site_pages p ON p.site_page_id=s.site_page_id
SET s.rows_json=JSON_REMOVE(
  s.rows_json,
  CASE
    WHEN JSON_SEARCH(s.rows_json,'one','What Is Toinky',NULL,'$[*].title_text') IS NOT NULL
      THEN REPLACE(JSON_UNQUOTE(JSON_SEARCH(s.rows_json,'one','What Is Toinky',NULL,'$[*].title_text')),'.title_text','')
    WHEN JSON_SEARCH(s.rows_json,'one','What Is Toinky',NULL,'$[*].eyebrow_text') IS NOT NULL
      THEN REPLACE(JSON_UNQUOTE(JSON_SEARCH(s.rows_json,'one','What Is Toinky',NULL,'$[*].eyebrow_text')),'.eyebrow_text','')
    WHEN JSON_SEARCH(s.rows_json,'one','What Is Toinky',NULL,'$[*].label_text') IS NOT NULL
      THEN REPLACE(JSON_UNQUOTE(JSON_SEARCH(s.rows_json,'one','What Is Toinky',NULL,'$[*].label_text')),'.label_text','')
  END
)
WHERE p.page_code='HOME'
  AND s.rows_json IS NOT NULL
  AND JSON_SEARCH(s.rows_json,'one','What Is Toinky',NULL,'$[*].title_text','$[*].eyebrow_text','$[*].label_text') IS NOT NULL;
