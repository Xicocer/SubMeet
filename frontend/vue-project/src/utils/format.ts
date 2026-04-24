export const formatDate = (value?: string | null) => {
  if (!value) return 'Не указано'

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return 'Не указано'
  }

  return new Intl.DateTimeFormat('ru-RU', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(date)
}

export const formatDateTime = (value?: string | null) => {
  if (!value) return 'Время не указано'

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return 'Время не указано'
  }

  return new Intl.DateTimeFormat('ru-RU', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)
}

export const formatPrice = (value: number | string) => {
  const numericValue = Number(value)

  if (Number.isNaN(numericValue)) {
    return 'Цена уточняется'
  }

  return new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
  }).format(numericValue)
}

export const formatDateForInput = (value?: string | null) => {
  if (!value) return ''

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return ''
  }

  const year = date.getFullYear()
  const month = `${date.getMonth() + 1}`.padStart(2, '0')
  const day = `${date.getDate()}`.padStart(2, '0')

  return `${year}-${month}-${day}`
}

export const formatDateTimeForInput = (value?: string | null) => {
  if (!value) return ''

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return ''
  }

  const year = date.getFullYear()
  const month = `${date.getMonth() + 1}`.padStart(2, '0')
  const day = `${date.getDate()}`.padStart(2, '0')
  const hours = `${date.getHours()}`.padStart(2, '0')
  const minutes = `${date.getMinutes()}`.padStart(2, '0')

  return `${year}-${month}-${day}T${hours}:${minutes}`
}

export const getInitials = (value?: string | null) => {
  if (!value) return 'SM'

  return value
    .split(' ')
    .filter(Boolean)
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()
}
