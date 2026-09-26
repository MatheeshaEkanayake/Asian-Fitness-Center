// Backend: gym-management-backend/app/Http/Controllers/Api/GymSettingsController.php
//   getGymSettings()             → GET /api/setup/gym          (setup.manage)
//   updateGymSettings(input)     → PUT /api/setup/gym          (multipart, for the optional logo file)
//   getGymBranding()             → GET /api/gym/branding       (public — used by the sign-in pages)
//
// The gym's profile (name, tagline, contact details, logo) lives in the
// database so a rebrand is a Setup change, not a code change. The logo is
// served through GET /api/gym/logo (its URL comes back as `logoUrl`), not
// /storage, so it works even if the storage symlink is missing.

import { apiClient } from './apiClient'

export function getGymSettings() {
  return apiClient.get('/setup/gym')
}

export function getGymBranding() {
  return apiClient.get('/gym/branding')
}

export function updateGymSettings({ name, tagline, email, phone, address, website, logoFile, removeLogo }) {
  const formData = new FormData()
  formData.append('name', name)
  formData.append('tagline', tagline || '')
  formData.append('email', email || '')
  formData.append('phone', phone || '')
  formData.append('address', address || '')
  formData.append('website', website || '')
  if (logoFile) formData.append('logo', logoFile)
  else if (removeLogo) formData.append('remove_logo', '1')
  return apiClient.upload('/setup/gym', formData, 'PUT')
}
