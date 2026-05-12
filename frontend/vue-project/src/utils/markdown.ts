const escapeHtml = (value: string) => value
  .replace(/&/g, '&amp;')
  .replace(/</g, '&lt;')
  .replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;')
  .replace(/'/g, '&#39;')

const normalizeUrl = (value: string) => {
  const trimmed = value.trim()

  if (!/^https?:\/\//i.test(trimmed)) {
    return null
  }

  return trimmed.replace(/"/g, '&quot;')
}

const renderInlineMarkdown = (value: string) => {
  let html = escapeHtml(value)

  html = html.replace(/`([^`\n]+)`/g, '<code>$1</code>')
  html = html.replace(/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/g, (_, label: string, url: string) => {
    const safeUrl = normalizeUrl(url)

    if (!safeUrl) {
      return label
    }

    return `<a href="${safeUrl}" target="_blank" rel="noreferrer noopener">${label}</a>`
  })
  html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
  html = html.replace(/(^|[^\*])\*([^*\n]+)\*(?!\*)/g, '$1<em>$2</em>')

  return html
}

const wrapParagraph = (lines: string[]) => {
  if (lines.length === 0) {
    return ''
  }

  return `<p>${lines.map(renderInlineMarkdown).join('<br>')}</p>`
}

export const renderMarkdown = (source: string) => {
  const input = source.replace(/\r\n/g, '\n').trim()

  if (!input) {
    return ''
  }

  const lines = input.split('\n')
  const blocks: string[] = []
  let paragraph: string[] = []
  let unorderedItems: string[] = []
  let orderedItems: string[] = []
  let codeLines: string[] = []
  let inCodeBlock = false

  const flushParagraph = () => {
    const block = wrapParagraph(paragraph)

    if (block) {
      blocks.push(block)
    }

    paragraph = []
  }

  const flushUnorderedList = () => {
    if (unorderedItems.length > 0) {
      blocks.push(`<ul>${unorderedItems.join('')}</ul>`)
      unorderedItems = []
    }
  }

  const flushOrderedList = () => {
    if (orderedItems.length > 0) {
      blocks.push(`<ol>${orderedItems.join('')}</ol>`)
      orderedItems = []
    }
  }

  const flushCodeBlock = () => {
    if (codeLines.length > 0) {
      blocks.push(`<pre><code>${escapeHtml(codeLines.join('\n'))}</code></pre>`)
      codeLines = []
    }
  }

  const flushAll = () => {
    flushParagraph()
    flushUnorderedList()
    flushOrderedList()
  }

  for (const line of lines) {
    if (line.trim().startsWith('```')) {
      if (inCodeBlock) {
        flushCodeBlock()
        inCodeBlock = false
      } else {
        flushAll()
        inCodeBlock = true
      }

      continue
    }

    if (inCodeBlock) {
      codeLines.push(line)
      continue
    }

    const trimmed = line.trim()

    if (!trimmed) {
      flushAll()
      continue
    }

    const headingMatch = trimmed.match(/^(#{1,4})\s+(.+)$/)

    if (headingMatch) {
      flushAll()
      const [, headingHashes = '', headingContent = ''] = headingMatch
      const level = Math.min(headingHashes.length + 2, 6)
      blocks.push(`<h${level}>${renderInlineMarkdown(headingContent)}</h${level}>`)
      continue
    }

    const unorderedMatch = trimmed.match(/^[-*]\s+(.+)$/)

    if (unorderedMatch) {
      flushParagraph()
      flushOrderedList()
      const [, unorderedContent = ''] = unorderedMatch
      unorderedItems.push(`<li>${renderInlineMarkdown(unorderedContent)}</li>`)
      continue
    }

    const orderedMatch = trimmed.match(/^\d+\.\s+(.+)$/)

    if (orderedMatch) {
      flushParagraph()
      flushUnorderedList()
      const [, orderedContent = ''] = orderedMatch
      orderedItems.push(`<li>${renderInlineMarkdown(orderedContent)}</li>`)
      continue
    }

    paragraph.push(trimmed)
  }

  if (inCodeBlock) {
    flushCodeBlock()
  }

  flushAll()

  return blocks.join('')
}
