-- Keep non-Hero section rows inside one Container at the database boundary.
DROP TRIGGER IF EXISTS trg_page_sections_container_insert;
CREATE TRIGGER trg_page_sections_container_insert
BEFORE INSERT ON site_page_sections
FOR EACH ROW
BEGIN
    IF NEW.section_code <> 'HERO'
       AND NEW.rows_json IS NOT NULL
       AND JSON_VALID(NEW.rows_json)
       AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(NEW.rows_json,'$[0].layout_type')),'') <> 'CONTAINER' THEN
        SET NEW.rows_json=JSON_ARRAY(
            JSON_OBJECT(
                'layout_type','CONTAINER',
                'active',true,
                'children',JSON_EXTRACT(NEW.rows_json,'$')
            )
        );
    END IF;
END;

DROP TRIGGER IF EXISTS trg_page_sections_container_update;
CREATE TRIGGER trg_page_sections_container_update
BEFORE UPDATE ON site_page_sections
FOR EACH ROW
BEGIN
    IF NEW.section_code <> 'HERO'
       AND NEW.rows_json IS NOT NULL
       AND JSON_VALID(NEW.rows_json)
       AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(NEW.rows_json,'$[0].layout_type')),'') <> 'CONTAINER' THEN
        SET NEW.rows_json=JSON_ARRAY(
            JSON_OBJECT(
                'layout_type','CONTAINER',
                'active',true,
                'children',JSON_EXTRACT(NEW.rows_json,'$')
            )
        );
    END IF;
END;
