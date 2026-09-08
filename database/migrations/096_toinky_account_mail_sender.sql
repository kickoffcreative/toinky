UPDATE platform_settings
SET setting_value='accounts@toinky.com'
WHERE setting_key='MAIL_FROM_ADDRESS'
  AND setting_value IN ('newsletter@sixpillarsmodel.com','no-reply@sixpillarsmodel.com');
