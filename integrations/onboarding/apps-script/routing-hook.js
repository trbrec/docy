if (payload && /^crm_onboarding_(health|document|signature|signature_status|signed_archive)$/.test(payload.action)) {
  try { return TRBCRM_contractJson_(TRBONB_command_(payload)); }
  catch (error) { return TRBCRM_contractJson_({success:false,error:error.message}); }
}
