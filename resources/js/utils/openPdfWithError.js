export const openPdfWithError = async (url, onError = message => window.alert(message)) => {
  const printWindow = window.open('about:blank', '_blank')

  if (!printWindow) {
    onError('El navegador bloqueó la ventana de impresión. Permita las ventanas emergentes e inténtelo de nuevo.')

    return
  }

  try {
    const response = await fetch(url, {
      credentials: 'same-origin',
      headers: { Accept: 'application/pdf, application/json' },
    })

    const contentType = response.headers.get('content-type') || ''

    if (!response.ok || !contentType.toLowerCase().includes('application/pdf')) {
      const responseText = await response.text()

      let message = response.ok
        ? 'El servidor no devolvió un PDF válido.'
        : `No se pudo generar el PDF (HTTP ${response.status}).`

      try {
        const payload = JSON.parse(responseText)

        message = payload.message || message
      } catch {
        if (contentType.toLowerCase().includes('text/plain') && responseText.trim())
          message = responseText.trim().slice(0, 500)
      }

      throw new Error(message)
    }

    const pdfUrl = URL.createObjectURL(await response.blob())

    printWindow.location.href = pdfUrl
    window.setTimeout(() => URL.revokeObjectURL(pdfUrl), 60_000)
  } catch (error) {
    printWindow.close()

    onError(error.message || 'No se pudo generar el PDF.')
  }
}
