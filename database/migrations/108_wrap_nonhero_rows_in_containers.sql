-- Normalize existing non-Hero section rows into one background-only Container.
UPDATE site_page_sections s
JOIN site_pages p ON p.site_page_id=s.site_page_id
SET s.rows_json=JSON_ARRAY(
    JSON_OBJECT(
        'layout_type','CONTAINER',
        'active',true,
        'children',CAST(s.rows_json AS JSON)
    )
)
WHERE p.page_code IS NOT NULL
  AND s.section_code <> 'HERO'
  AND s.rows_json IS NOT NULL
  AND JSON_VALID(s.rows_json)
  AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(s.rows_json,'$[0].layout_type')),'') <> 'CONTAINER';
