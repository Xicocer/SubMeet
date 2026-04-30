const digitsOnly = (value: string) => value.replace(/\D/g, '')

export const formatPhoneMask = (value: string) => {
  const source = value.trim()
  let digits = digitsOnly(source)

  if (digits.length === 0) {
    return ''
  }

  const usesEightPrefix = digits.startsWith('8') && !source.startsWith('+7')

  if (usesEightPrefix) {
    digits = digits.slice(0, 11)
  } else {
    if (digits.startsWith('7')) {
      digits = digits.slice(1)
    } else if (digits.startsWith('8')) {
      digits = digits.slice(1)
    }

    digits = digits.slice(0, 10)
  }

  const prefix = usesEightPrefix ? '8' : '+7'
  const area = digits.slice(0, 3)
  const first = digits.slice(3, 6)
  const second = digits.slice(6, 8)
  const third = digits.slice(8, 10)

  let result = prefix

  if (area) {
    result += ` (${area}`
  }

  if (area.length === 3) {
    result += ')'
  }

  if (first) {
    result += ` ${first}`
  }

  if (second) {
    result += `-${second}`
  }

  if (third) {
    result += `-${third}`
  }

  return result
}

export const isPhoneMaskComplete = (value: string) => {
  const digits = digitsOnly(value)

  if (digits.length === 11 && (digits.startsWith('7') || digits.startsWith('8'))) {
    return true
  }

  return digits.length === 10
}

export const normalizePhoneComparable = (value: string) => {
  const digits = digitsOnly(value)

  if (digits.length === 11 && digits.startsWith('8')) {
    return `7${digits.slice(1)}`
  }

  if (digits.length === 10) {
    return `7${digits}`
  }

  if (digits.length === 11 && digits.startsWith('7')) {
    return digits
  }

  return digits
}
