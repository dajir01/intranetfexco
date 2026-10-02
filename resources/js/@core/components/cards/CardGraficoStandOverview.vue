<script setup>
import { computed } from 'vue'

const props = defineProps({
  title: {
    type: String,
    default: 'Grafico de Stands',
  },
  items: {
    type: Array,
    required: true,
  },
})

const formatPercent = value => Number(value ?? 0).toFixed(1)

const normalizedItems = computed(() => props.items.map(item => ({
  ...item,
  percentage: Number(item.percentage ?? 0),
})))

const totalPercent = computed(() => normalizedItems.value
  .reduce((acc, item) => acc + item.percentage, 0))

const safeWidth = value => {
  const percent = Number(value ?? 0)
  const total = totalPercent.value

  if (percent <= 0 || total <= 0) return 0

  return (percent / total) * 100
}

const progressClass = index => {
  if (index === 0) return 'rounded-e-0 rounded-lg'
  if (index === props.items.length - 1) return 'rounded-s-0 rounded-lg'

  return 'rounded-0'
}
</script>

<template>
  <VCard>
    <VCardTitle>{{ title }}</VCardTitle>
    <VDivider />
    <VCardText>
      <div class="d-flex mb-6 w-100">
        <div
          v-for="(item, index) in normalizedItems"
          :key="item.title"
          :style="{ inlineSize: `${safeWidth(item.percentage)}%` }"
        >
          <div class="vehicle-progress-label position-relative mb-6 text-body-1 d-none d-sm-block">
            {{ item.title }}
          </div>
          <VProgressLinear
            :color="item.progressColor"
            model-value="100"
            height="46"
            :class="progressClass(index)"
          >
            <div :class="item.progressTextClass">
              {{ formatPercent(item.percentage) }}%
            </div>
          </VProgressLinear>
        </div>
      </div>
      <VTable class="text-no-wrap">
        <tbody>
          <tr
            v-for="(item, index) in normalizedItems"
            :key="index"
          >
            <td
              width="70%"
              style="padding-inline-start: 0 !important;"
            >
              <div class="d-flex align-center gap-x-2">
                <VIcon
                  :icon="item.icon"
                  size="24"
                  class="text-high-emphasis"
                />
                <div class="text-body-1 text-high-emphasis">
                  {{ item.title }}
                </div>
              </div>
            </td>
            <td>
              <h6 class="text-h6">
                {{ item.countLabel }}
              </h6>
            </td>
            <td>
              <div class="text-body-1">
                {{ formatPercent(item.percentage) }}%
              </div>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.vehicle-progress-label {
  padding-block-end: 1rem;
  max-inline-size: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;

  &::after {
    position: absolute;
    display: inline-block;
    background-color: rgba(var(--v-theme-on-surface), var(--v-border-opacity));
    block-size: 10px;
    content: "";
    inline-size: 2px;
    inset-block-end: 0;
    inset-inline-start: 0;

    [dir="rtl"] & {
      inset-inline: unset 0;
    }
  }
}
</style>

<style lang="scss">
.v-progress-linear__content {
  justify-content: start;
  padding-inline-start: 1rem;
}

@media (max-width: 1080px) {
  .v-progress-linear__content {
    padding-inline-start: 0.75rem !important;
  }
}

@media (max-width: 576px) {
  .v-progress-linear__content {
    padding-inline-start: 0.125rem !important;
  }
}
</style>
