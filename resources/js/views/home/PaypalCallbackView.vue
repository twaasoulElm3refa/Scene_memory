<template>
  <div class="flex items-center justify-center min-h-screen">
    <div class="text-center">
      <div v-if="loading">
        <p>جاري تأكيد الدفع...</p>
        <!-- spinner بتاعك -->
      </div>
      <div v-else-if="error">
        <p>{{ error }}</p>
        <router-link to="/">الرئيسية</router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { PaymentService } from '../../services/PaymentService'

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const error = ref(null)

onMounted(async () => {
  const token = route.query.token

  if (!token) {
    router.push('/failed')
    return
  }

  try {
    // بتكلم الـ API بتاعك
    const response = await PaymentService.paypalSuccess(token)

    if (response.data.success) {
      // polling على الـ order status لحد ما يبقى completed
      await pollOrderStatus(response.data.order_id)
    } else {
      router.push('/failed')
    }
  } catch (e) {
    router.push('/failed')
  }
})

async function pollOrderStatus(orderId) {
  const maxAttempts = 10
  let attempts = 0

  const poll = async () => {
    attempts++

    try {
      const res = await PaymentService.orderStatus(orderId)
      const status = res.data.status

      if (status === 'completed') {
        router.push('/success')
        return
      }

      if (status === 'failed') {
        router.push('/failed')
        return
      }

      if (attempts < maxAttempts) {
        setTimeout(poll, 2000) // كل 2 ثانية
      } else {
        // timeout — الـ webhook ممكن يتأخر شوية
        router.push('/success') // أو صفحة "جاري المعالجة"
      }
    } catch (e) {
      router.push('/failed')
    }
  }

  await poll()
}
</script>
