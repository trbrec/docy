if (payload && /^crm_onboarding_(health|document|signature|signature_status|signed_archive|drive_health|drive_folder|drive_grant|drive_verify|drive_read)$/.test(payload.action)) {
  try { return TRBCRM_contractJson_(TRBONB_command_(payload)); }
  catch (error) { return TRBCRM_contractJson_({success:false,error:error.message}); }
}
