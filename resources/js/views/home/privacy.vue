<template>
  <div class="min-h-screen bg-gradient-to-br from-gray-50 to-blue-50 py-8 px-4">
    <div class="max-w-4xl mx-auto">
      
      <!-- Hero Section -->
      <div class="text-center mb-12">
        <!-- <div class="flex justify-center mb-6">
          <div class="w-20 h-20 bg-gradient-to-br from-purple-500 to-blue-500 rounded-2xl flex items-center justify-center shadow-lg">
            <span class="text-3xl">🎞️</span>
          </div>
        </div> -->
        <h1 class="text-4xl md:text-5xl font-bold text-gray-800 mb-4">
          سياسة الخصوصية
        </h1>
        <p class="text-xl text-gray-600 max-w-3xl mx-auto">
          نحن في <span class="font-bold text-purple-600">Scene Memory</span> نحمي خصوصيتك ونضمن أمان ذكرياتك
        </p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
          <span class="px-4 py-2 bg-blue-100 text-blue-700 rounded-full text-sm font-medium">آمن</span>
          <span class="px-4 py-2 bg-green-100 text-green-700 rounded-full text-sm font-medium">شفاف</span>
          <span class="px-4 py-2 bg-purple-100 text-purple-700 rounded-full text-sm font-medium">موثوق</span>
          <span class="px-4 py-2 bg-orange-100 text-orange-700 rounded-full text-sm font-medium">خاص</span>
        </div>
      </div>

      <!-- Main Content -->
      <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100">
        
        <!-- Navigation Sidebar -->
        <div class="md:flex">
          <div class="md:w-1/4 bg-gradient-to-b from-gray-50 to-blue-50 p-6 border-r">
            <div class="sticky top-6">
              <h3 class="font-bold text-gray-700 mb-4 text-lg">فهرس المحتويات</h3>
              <nav class="space-y-2">
                <button 
                  v-for="section in sections" 
                  :key="section.id"
                  @click="scrollToSection(section.id)"
                  :class="[
                    'w-full text-right px-4 py-3 rounded-lg transition-all duration-300 flex items-center justify-between',
                    activeSection === section.id 
                      ? 'bg-white shadow-sm border border-blue-100 text-blue-600' 
                      : 'hover:bg-blue-50 text-gray-600'
                  ]"
                >
                  <span class="text-lg ml-2">{{ section.icon }}</span>
                  <span>{{ section.title }}</span>
                </button>
              </nav>
              
              <!-- Quick Info Box -->
              <div class="mt-8 p-4 bg-gradient-to-r from-blue-50 to-purple-50 rounded-xl border border-blue-100">
                <h4 class="font-bold text-gray-700 mb-2">⚡ ملخص سريع</h4>
                <ul class="space-y-2 text-sm text-gray-600">
                  <li class="flex items-center">
                    <span class="text-green-500 ml-2">✓</span>
                    <span>بياناتك مشفرة بأمان</span>
                  </li>
                  <li class="flex items-center">
                    <span class="text-green-500 ml-2">✓</span>
                    <span>لا نشارك بياناتك مع طرف ثالث</span>
                  </li>
                  <li class="flex items-center">
                    <span class="text-green-500 ml-2">✓</span>
                    <span>تحكم كامل في بياناتك</span>
                  </li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Content Area -->
          <div class="md:w-3/4 p-6 md:p-8">
            <!-- Introduction -->
            <section id="intro" class="mb-12 scroll-mt-20">
              <div class="flex items-center mb-6">
                <div class="w-12 h-12 bg-gradient-to-r from-blue-100 to-purple-100 rounded-xl flex items-center justify-center ml-4">
                  <span class="text-2xl">👋</span>
                </div>
                <h2 class="text-2xl font-bold text-gray-800">مرحباً بك في Scene Memory</h2>
              </div>
              <div class="bg-gradient-to-r from-blue-50 to-transparent p-6 rounded-xl">
                <p class="text-gray-700 leading-relaxed mb-4">
                  تطبيق <span class="font-bold text-blue-600">Scene Memory</span> هو رفيقك الذكي لحفظ وتنظيم الأحداث والذكريات المهمة في حياتك. نحن ندرك أن هذه الذكريات ثمينة وشخصية، ولذلك نلتزم بحماية خصوصيتك بأعلى معايير الأمان.
                </p>
                <p class="text-gray-700 leading-relaxed">
                  تشرح هذه السياسة كيف نجمع ونستخدم ونحمي معلوماتك الشخصية عند استخدامك لتطبيق Scene Memory.
                </p>
              </div>
            </section>

            <!-- Data Collection -->
            <section id="data-collection" class="mb-12 scroll-mt-20">
              <div class="flex items-center mb-6">
                <div class="w-12 h-12 bg-gradient-to-r from-green-100 to-teal-100 rounded-xl flex items-center justify-center ml-4">
                  <span class="text-2xl">📊</span>
                </div>
                <h2 class="text-2xl font-bold text-gray-800">البيانات التي نجمعها</h2>
              </div>
              
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="bg-gradient-to-br from-green-50 to-white p-6 rounded-xl border border-green-100">
                  <div class="flex items-center mb-4">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center ml-3">
                      <span class="text-xl">👤</span>
                    </div>
                    <h3 class="font-bold text-gray-800">معلومات الحساب</h3>
                  </div>
                  <ul class="space-y-3 text-gray-700">
                    <li class="flex items-start">
                      <span class="text-green-500 mt-1 ml-2">•</span>
                      <span>الاسم والبريد الإلكتروني</span>
                    </li>
                    <li class="flex items-start">
                      <span class="text-green-500 mt-1 ml-2">•</span>
                      <span>صورة الملف الشخصي (اختيارية)</span>
                    </li>
                    <li class="flex items-start">
                      <span class="text-green-500 mt-1 ml-2">•</span>
                      <span>تفضيلات الإشعارات</span>
                    </li>
                  </ul>
                </div>

                <div class="bg-gradient-to-br from-blue-50 to-white p-6 rounded-xl border border-blue-100">
                  <div class="flex items-center mb-4">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center ml-3">
                      <span class="text-xl">🎞️</span>
                    </div>
                    <h3 class="font-bold text-gray-800">الذكريات والأحداث</h3>
                  </div>
                  <ul class="space-y-3 text-gray-700">
                    <li class="flex items-start">
                      <span class="text-blue-500 mt-1 ml-2">•</span>
                      <span>النصوص والملاحظات</span>
                    </li>
                    <li class="flex items-start">
                      <span class="text-blue-500 mt-1 ml-2">•</span>
                      <span>الصور والفيديوهات (اختيارية)</span>
                    </li>
                    <li class="flex items-start">
                      <span class="text-blue-500 mt-1 ml-2">•</span>
                      <span>التواريخ والأوقات</span>
                    </li>
                    <li class="flex items-start">
                      <span class="text-blue-500 mt-1 ml-2">•</span>
                      <span>الموقع الجغرافي (اختياري)</span>
                    </li>
                  </ul>
                </div>
              </div>

              <div class="bg-gradient-to-r from-gray-50 to-white p-6 rounded-xl border">
                <div class="flex items-center mb-4">
                  <span class="text-2xl ml-3">⚠️</span>
                  <h3 class="font-bold text-gray-800">ماذا لا نجمع</h3>
                </div>
                <p class="text-gray-700">
                  نحن <span class="font-bold">لا نجمع</span> معلومات حساسة مثل بيانات البطاقات البنكية أو المعلومات الطبية أو أي بيانات لا تتعلق مباشرة بوظيفة التطبيق.
                </p>
              </div>
            </section>

            <!-- Data Usage -->
            <section id="data-usage" class="mb-12 scroll-mt-20">
              <div class="flex items-center mb-6">
                <div class="w-12 h-12 bg-gradient-to-r from-purple-100 to-pink-100 rounded-xl flex items-center justify-center ml-4">
                  <span class="text-2xl">🎯</span>
                </div>
                <h2 class="text-2xl font-bold text-gray-800">كيف نستخدم بياناتك</h2>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center p-6 bg-gradient-to-b from-white to-purple-50 rounded-xl border border-purple-100 hover:shadow-md transition-shadow">
                  <div class="w-16 h-16 bg-gradient-to-r from-purple-100 to-pink-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="text-2xl">💾</span>
                  </div>
                  <h3 class="font-bold text-gray-800 mb-3">تخزين الذكريات</h3>
                  <p class="text-gray-600 text-sm">نحفظ أحداثك وآمنة لتعود إليها متى شئت</p>
                </div>

                <div class="text-center p-6 bg-gradient-to-b from-white to-blue-50 rounded-xl border border-blue-100 hover:shadow-md transition-shadow">
                  <div class="w-16 h-16 bg-gradient-to-r from-blue-100 to-cyan-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="text-2xl">🔔</span>
                  </div>
                  <h3 class="font-bold text-gray-800 mb-3">تذكيرات ذكية</h3>
                  <p class="text-gray-600 text-sm">نذكرك بالأحداث المهمة في تواريخها</p>
                </div>

                <div class="text-center p-6 bg-gradient-to-b from-white to-green-50 rounded-xl border border-green-100 hover:shadow-md transition-shadow">
                  <div class="w-16 h-16 bg-gradient-to-r from-green-100 to-teal-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="text-2xl">📈</span>
                  </div>
                  <h3 class="font-bold text-gray-800 mb-3">تحسين التطبيق</h3>
                  <p class="text-gray-600 text-sm">نطور تجربتك بناءً على استخدامك الآمن</p>
                </div>
              </div>
            </section>

            <!-- Data Protection -->
            <section id="data-protection" class="mb-12 scroll-mt-20">
              <div class="flex items-center mb-6">
                <div class="w-12 h-12 bg-gradient-to-r from-red-100 to-orange-100 rounded-xl flex items-center justify-center ml-4">
                  <span class="text-2xl">🛡️</span>
                </div>
                <h2 class="text-2xl font-bold text-gray-800">كيف نحمي بياناتك</h2>
              </div>

              <div class="bg-gradient-to-r from-red-50 to-orange-50 p-8 rounded-2xl border border-red-100">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                  <div>
                    <h3 class="font-bold text-gray-800 mb-4 flex items-center">
                      <span class="text-red-500 ml-2">🔐</span>
                      <span>التشفير المتقدم</span>
                    </h3>
                    <ul class="space-y-3 text-gray-700">
                      <li class="flex items-center">
                        <span class="w-2 h-2 bg-red-400 rounded-full ml-3"></span>
                        <span>تشفير AES-256 لجميع البيانات</span>
                      </li>
                      <li class="flex items-center">
                        <span class="w-2 h-2 bg-red-400 rounded-full ml-3"></span>
                        <span>اتصالات آمنة (HTTPS/TLS)</span>
                      </li>
                      <li class="flex items-center">
                        <span class="w-2 h-2 bg-red-400 rounded-full ml-3"></span>
                        <span>نسخ احتياطية مشفرة</span>
                      </li>
                    </ul>
                  </div>

                  <div>
                    <h3 class="font-bold text-gray-800 mb-4 flex items-center">
                      <span class="text-orange-500 ml-2">👁️</span>
                      <span>الخصوصية</span>
                    </h3>
                    <ul class="space-y-3 text-gray-700">
                      <li class="flex items-center">
                        <span class="w-2 h-2 bg-orange-400 rounded-full ml-3"></span>
                        <span>بياناتك لا تُباع لأطراف ثالثة</span>
                      </li>
                      <li class="flex items-center">
                        <span class="w-2 h-2 bg-orange-400 rounded-full ml-3"></span>
                        <span>خوادمنا في مواقع آمنة</span>
                      </li>
                      <li class="flex items-center">
                        <span class="w-2 h-2 bg-orange-400 rounded-full ml-3"></span>
                        <span>مراجعات أمنية دورية</span>
                      </li>
                    </ul>
                  </div>
                </div>

                <div class="mt-8 pt-6 border-t border-red-200">
                  <div class="flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-r from-red-200 to-orange-200 rounded-full flex items-center justify-center ml-4">
                      <span class="text-xl">📋</span>
                    </div>
                    <h4 class="font-bold text-gray-800">التزامنا بالأمان</h4>
                  </div>
                  <p class="text-gray-700 mt-3">
                    نلتزم بالامتثال لأعلى معايير حماية البيانات العالمية، بما في ذلك اللوائح المحلية والدولية لحماية الخصوصية.
                  </p>
                </div>
              </div>
            </section>

            <!-- Your Rights -->
            <section id="your-rights" class="mb-12 scroll-mt-20">
              <div class="flex items-center mb-6">
                <div class="w-12 h-12 bg-gradient-to-r from-yellow-100 to-amber-100 rounded-xl flex items-center justify-center ml-4">
                  <span class="text-2xl">⚖️</span>
                </div>
                <h2 class="text-2xl font-bold text-gray-800">حقوقك</h2>
              </div>

              <div class="space-y-6">
                <div v-for="right in rights" :key="right.id" 
                     class="flex items-start p-5 bg-gradient-to-r from-white to-gray-50 rounded-xl border hover:shadow-sm transition-all">
                  <div class="w-12 h-12 rounded-lg flex items-center justify-center ml-4 flex-shrink-0"
                       :class="right.color">
                    <span class="text-xl">{{ right.icon }}</span>
                  </div>
                  <div>
                    <h3 class="font-bold text-gray-800 mb-2">{{ right.title }}</h3>
                    <p class="text-gray-700">{{ right.description }}</p>
                  </div>
                </div>
              </div>
            </section>

            <!-- Updates -->
            <section id="updates" class="mb-12 scroll-mt-20">
              <div class="bg-gradient-to-r from-gray-100 to-white p-8 rounded-2xl border">
                <div class="flex items-center mb-6">
                  <div class="w-12 h-12 bg-gradient-to-r from-gray-200 to-blue-200 rounded-xl flex items-center justify-center ml-4">
                    <span class="text-2xl">🔄</span>
                  </div>
                  <h2 class="text-2xl font-bold text-gray-800">تحديثات السياسة</h2>
                </div>
                
                <div class="bg-white p-6 rounded-xl border border-gray-200">
                  <p class="text-gray-700 mb-4">
                    قد نقوم بتحديث سياسة الخصوصية هذه من وقت لآخر. سنخطرك بأي تغييرات جوهرية عن طريق نشر الإشعار في التطبيق أو عبر البريد الإلكتروني.
                  </p>
                  <div class="flex items-center justify-between p-4 bg-blue-50 rounded-lg">
                    <div class="flex items-center">
                      <span class="text-blue-500 text-2xl ml-3">📅</span>
                      <div>
                        <p class="font-bold text-gray-800">تاريخ آخر تحديث</p>
                        <p class="text-gray-600">{{ lastUpdateDate }}</p>
                      </div>
                    </div>
                    <button class="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition">
                      مشاهدة النسخ السابقة
                    </button>
                  </div>
                </div>
              </div>
            </section>

            <!-- Contact -->
            <section id="contact" class="scroll-mt-20">
              <div class="bg-gradient-to-r from-blue-50 to-cyan-50 p-8 rounded-2xl border border-blue-200">
                <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">هل لديك أسئلة؟</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                  <div class="text-center">
                    <div class="w-16 h-16 bg-gradient-to-r from-blue-100 to-cyan-100 rounded-full flex items-center justify-center mx-auto mb-4">
                      <span class="text-2xl">📧</span>
                    </div>
                    <h3 class="font-bold text-gray-800 mb-2">البريد الإلكتروني</h3>
                    <a href="mailto:privacy@scenememory.com" class="text-blue-600 hover:text-blue-800">
                      privacy@scenememory.com
                    </a>
                    <p class="text-gray-600 text-sm mt-2">رد خلال 24-48 ساعة</p>
                  </div>

                  <div class="text-center">
                    <div class="w-16 h-16 bg-gradient-to-r from-green-100 to-teal-100 rounded-full flex items-center justify-center mx-auto mb-4">
                      <span class="text-2xl">💬</span>
                    </div>
                    <h3 class="font-bold text-gray-800 mb-2">الدعم الفني</h3>
                    <a href="#" class="text-green-600 hover:text-green-800">
                      الدردشة المباشرة
                    </a>
                    <p class="text-gray-600 text-sm mt-2">متاح 24/7</p>
                  </div>
                </div>

                <div class="mt-8 pt-6 border-t border-blue-200">
                  <p class="text-center text-gray-700">
                    نحن هنا للإجابة على جميع استفساراتك حول خصوصيتك وأمان بياناتك
                  </p>
                </div>
              </div>
            </section>

            <!-- Acceptance -->
            <div class="mt-12 p-6 bg-gradient-to-r from-green-50 to-emerald-50 rounded-2xl border border-green-200">
              <div class="flex items-center justify-between flex-wrap">
                <div class="flex items-center mb-4 md:mb-0">
                  <span class="text-2xl text-green-600 ml-3">✅</span>
                  <div>
                    <p class="font-bold text-gray-800">باستخدامك تطبيق Scene Memory، فإنك توافق على سياسة الخصوصية هذه</p>
                    <p class="text-gray-600 text-sm">نشكرك على ثقتك بنا لحفظ ذكرياتك الثمينة</p>
                  </div>
                </div>
                <button class="px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-500 text-white font-bold rounded-lg hover:shadow-lg transition-all">
                  موافق على السياسة
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="mt-8 text-center text-gray-500 text-sm">
        <p>© 2024 Scene Memory. جميع الحقوق محفوظة.</p>
        <p class="mt-2">نحمي ذكرياتك كما تحميها بنفسك</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const activeSection = ref('intro')
const lastUpdateDate = ref('1 يناير 2024')

const sections = [
  { id: 'intro', title: 'مقدمة', icon: '👋' },
  { id: 'data-collection', title: 'البيانات المجمعة', icon: '📊' },
  { id: 'data-usage', title: 'استخدام البيانات', icon: '🎯' },
  { id: 'data-protection', title: 'حماية البيانات', icon: '🛡️' },
  { id: 'your-rights', title: 'حقوقك', icon: '⚖️' },
  { id: 'updates', title: 'التحديثات', icon: '🔄' },
  { id: 'contact', title: 'اتصل بنا', icon: '📞' }
]

const rights = [
  {
    id: 1,
    title: 'حق الوصول',
    description: 'يمكنك الوصول إلى جميع بياناتك الشخصية المخزنة في أي وقت',
    icon: '👁️',
    color: 'bg-gradient-to-r from-blue-100 to-cyan-100'
  },
  {
    id: 2,
    title: 'حق التصحيح',
    description: 'يمكنك طلب تصحيح أي بيانات غير دقيقة أو غير مكتملة',
    icon: '✏️',
    color: 'bg-gradient-to-r from-green-100 to-teal-100'
  },
  {
    id: 3,
    title: 'حق الحذف',
    description: 'يمكنك طلب حذف بياناتك الشخصية في أي وقت',
    icon: '🗑️',
    color: 'bg-gradient-to-r from-red-100 to-pink-100'
  },
  {
    id: 4,
    title: 'حق التنقل',
    description: 'يمكنك نقل بياناتك إلى خدمات أخرى عند الرغبة',
    icon: '📤',
    color: 'bg-gradient-to-r from-purple-100 to-indigo-100'
  },
  {
    id: 5,
    title: 'حق الاعتراض',
    description: 'يمكنك الاعتراض على معالجة بياناتك لأغراض معينة',
    icon: '✋',
    color: 'bg-gradient-to-r from-yellow-100 to-amber-100'
  }
]

const scrollToSection = (id) => {
  const element = document.getElementById(id)
  if (element) {
    element.scrollIntoView({ behavior: 'smooth' })
    activeSection.value = id
  }
}

const handleScroll = () => {
  const scrollPosition = window.scrollY + 100
  
  for (const section of sections) {
    const element = document.getElementById(section.id)
    if (element) {
      const offsetTop = element.offsetTop
      const offsetHeight = element.offsetHeight
      
      if (scrollPosition >= offsetTop && scrollPosition < offsetTop + offsetHeight) {
        activeSection.value = section.id
        break
      }
    }
  }
}

onMounted(() => {
  window.addEventListener('scroll', handleScroll)
  // Set current date for last update
  const today = new Date()
  lastUpdateDate.value = today.toLocaleDateString('ar-EG', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
})

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll)
})
</script>

<style scoped>
.scroll-mt-20 {
  scroll-margin-top: 5rem;
}

/* Custom scrollbar for the main content */
::-webkit-scrollbar {
  width: 8px;
}

::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 10px;
}

::-webkit-scrollbar-thumb {
  background: linear-gradient(to bottom, #667eea, #764ba2);
  border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(to bottom, #5a67d8, #6b46c1);
}

/* Smooth transitions */
* {
  transition: background-color 0.3s ease, border-color 0.3s ease;
}

/* Gradient text for important elements */
.gradient-text {
  background: linear-gradient(to right, #667eea, #764ba2);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

/* Responsive design improvements */
@media (max-width: 768px) {
  .text-4xl {
    font-size: 2rem;
  }
  
  .text-2xl {
    font-size: 1.5rem;
  }
}
</style>