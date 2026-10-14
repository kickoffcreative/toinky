-- Enable MEDIUM and LARGE version generation by default on all uploads.
-- Previously these defaulted to '0' (disabled); new uploads will now generate
-- optimized versions automatically. Existing images can be regenerated via
-- the 'Regenerate Versions' button in the media library.

UPDATE platform_settings SET setting_value='1' WHERE setting_key='MEDIA_GENERATE_MEDIUM';
UPDATE platform_settings SET setting_value='1' WHERE setting_key='MEDIA_GENERATE_LARGE';
