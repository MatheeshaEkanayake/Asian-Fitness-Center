// Backend: gym-management-backend/app/Http/Controllers/Api/AttendanceController.php
//   listAttendance() → GET /api/attendance
//
// NOTE: new file — attendance previously read the static `initialAttendance`
// array straight out of src/data/mockData.js (bypassing services entirely).
// The controller supports server-side ?search/?status/?sort/?per_page, but
// MemberAttendencePage.jsx keeps its existing client-side filter/sort/paginate
// logic unchanged, so this just fetches every record (a high per_page) once.

import { apiClient } from './apiClient'

export async function listAttendance() {
  const page = await apiClient.get('/attendance?per_page=1000')
  return page.data
}

// Staff check-ins from the door device (Members › Staff Attendance).
//   listStaffAttendance() → GET /api/staff-attendance
export async function listStaffAttendance() {
  const page = await apiClient.get('/staff-attendance?per_page=1000')
  return page.data
}
