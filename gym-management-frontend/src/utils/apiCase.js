// Converts between the Laravel API's snake_case JSON and the frontend's
// camelCase convention (matches the rest of the app — see fullName/joinDate
// etc. in src/services/memberService.js's mock shape). Deep/recursive so
// nested relations (e.g. a user's `role` object) and paginator envelopes
// (Laravel's `data: [...]`) are converted too.

function snakeToCamel(str) {
  return str.replace(/_([a-z0-9])/g, (_, c) => c.toUpperCase())
}

function camelToSnake(str) {
  return str.replace(/[A-Z]/g, (c) => `_${c.toLowerCase()}`)
}

export function keysToCamel(input) {
  if (Array.isArray(input)) return input.map(keysToCamel)
  if (input === null || typeof input !== 'object' || input instanceof File) return input
  return Object.fromEntries(
    Object.entries(input).map(([key, value]) => [snakeToCamel(key), keysToCamel(value)])
  )
}

export function keysToSnake(input) {
  if (Array.isArray(input)) return input.map(keysToSnake)
  if (input === null || typeof input !== 'object' || input instanceof File) return input
  return Object.fromEntries(
    Object.entries(input).map(([key, value]) => [camelToSnake(key), keysToSnake(value)])
  )
}
