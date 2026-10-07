export const userAreaOptions = [
  { value: 1, title: 'Sistemas', text: 'Sistemas' },
  { value: 2, title: 'Comercial', text: 'Comercial' },
  { value: 3, title: 'Administración', text: 'Administración' },
  { value: 4, title: 'Legal', text: 'Legal' },
  { value: 5, title: 'Gerencia General', text: 'Gerencia General' },
  { value: 6, title: 'Comunicación', text: 'Comunicación' },
  { value: 7, title: 'Auditoría', text: 'Auditoría' },
  { value: 8, title: 'Almacén', text: 'Almacén' },
  { value: 9, title: 'Operaciones', text: 'Operaciones' },
  { value: 10, title: 'Técnica Eléctrica', text: 'Técnica Eléctrica' },
  { value: 11, title: 'Eventos', text: 'Eventos' },
  { value: 12, title: 'Secretaría', text: 'Secretaría' },
]

const normalizeArea = value => String(value ?? '')
  .normalize('NFD')
  .replace(/[\u0300-\u036f]/g, '')
  .trim()
  .toLowerCase()
  .replace(/\s+/g, ' ')

const areaAliases = {
  'administracion y finanzas': 'administracion',
  gerencia: 'gerencia general',
  'auditoria interna': 'auditoria',
}

export const getUserAreaLabel = nivel => {
  const numericLevel = Number(nivel)

  return userAreaOptions.find(area => area.value === numericLevel)?.text || null
}

export const getUserAreaLevel = areaName => {
  const normalizedArea = normalizeArea(areaName)
  const canonicalArea = areaAliases[normalizedArea] || normalizedArea

  return userAreaOptions.find(area => normalizeArea(area.text) === canonicalArea)?.value || null
}
