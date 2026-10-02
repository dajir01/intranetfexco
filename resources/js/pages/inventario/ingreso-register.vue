<script setup>
import { ref, onMounted } from 'vue'
import InvoiceEditable from '@/views/app/invoice/InvoiceEditable.vue'

// Datos para la factura editable
const invoiceData = ref({
  invoice: {
    id: '',
    numero: '',
    issuedDate: '',
    total: 0,
    client: {
      name: '',
      company: '',
    },
  },
  purchasedProducts: [
    {
      title: '',
      cost: 0,
      hours: 0,
      description: '',
    },
  ],
  note: '',
  salesperson: '',
})

const addProduct = value => {
  invoiceData.value?.purchasedProducts.push(value)
}

const removeProduct = id => {
  invoiceData.value?.purchasedProducts.splice(id, 1)
}

// Cargar siguiente número de ingreso
onMounted(async () => {
  try {
    const r = await fetch('/inventario/ingresos/next-numero')
    if (r.ok) {
      const j = await r.json()

      invoiceData.value.invoice.numero = j.numero

      const formatted = String(j.numero).padStart(6, '0')

      invoiceData.value.invoice.id = `NI-${formatted}`
    }
  } catch (e) {
    // Silenciar errores de red
  }
})
</script>

<template>
  <VRow>
    <VCol
      cols="12"
      md="12"
    >
      <InvoiceEditable
        :data="invoiceData"
        @push="addProduct"
        @remove="removeProduct"
      />
    </VCol>
  </VRow>
</template>
