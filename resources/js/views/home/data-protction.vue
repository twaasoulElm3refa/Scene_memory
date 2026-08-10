<template>
  <div class="scemory-page legal-page data-protection-page">
    <!-- Header -->
    <header class="app-header">
      <div class="container">
        <div class="header-content">
          <div class="logo">
            <div class="logo-icon">
              <i class="fas fa-brain"></i>
            </div>
            <div class="logo-text">
              <h1>Scene Memory</h1>
              <p>تخزين وتنظيم الذكريات الرقمية بأمان</p>
            </div>
          </div>
          
          <nav class="main-nav">
            <ul>
              <li v-for="item in navItems" :key="item.id">
                <a 
                  :href="item.link" 
                  :class="{ active: activeNav === item.id }"
                  @click.prevent="setActiveNav(item.id)"
                >
                  {{ item.text }}
                </a>
              </li>
            </ul>
          </nav>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
      <div class="container">
        <!-- Page Header -->
        <section class="page-header">
          <h2>حماية البيانات في Scene Memory</h2>
          <p>نحن نعتبر خصوصية بياناتك وأمانها من أهم أولوياتنا. تعرف على كيفية حماية مشروع Scene Memory لبياناتك الشخصية والذكريات الرقمية.</p>
        </section>

        <!-- Data Protection Commitment -->
        <DataProtectionCard 
          :icon="'shield-alt'"
          :title="'التزامنا بحماية بياناتك'"
        >
          <p>في Scene Memory، نفهم أهمية الذكريات الرقمية التي تودعها لدينا، ولهذا نلتزم بأعلى معايير حماية البيانات. نحن نتبع أفضل الممارسات الأمنية لنضمن أن بياناتك تبقى خاصة وآمنة في جميع الأوقات.</p>
          <p>نلتزم بتطبيق لوائح حماية البيانات العالمية بما في ذلك النظام الأوروبي العام لحماية البيانات (GDPR) وأنظمة الخليج العربي لحماية البيانات الشخصية.</p>
        </DataProtectionCard>

        <!-- Data Types -->
        <DataProtectionCard 
          :icon="'database'"
          :title="'أنواع البيانات التي نجمعها'"
        >
          <p>نجمع فقط البيانات الضرورية لتقديم خدمة Scene Memory بشكل فعال. تشمل هذه البيانات:</p>
          
          <div class="data-types-grid">
            <div 
              v-for="type in dataTypes" 
              :key="type.id"
              class="data-type-card"
              :style="{ borderRightColor: type.color }"
            >
              <h4>{{ type.title }}</h4>
              <p>{{ type.description }}</p>
            </div>
          </div>
        </DataProtectionCard>

        <!-- Protection Principles -->
        <DataProtectionCard 
          :icon="'user-lock'"
          :title="'مبادئ حماية البيانات'"
        >
          <p>نعمل وفقًا للمبادئ الأساسية التالية في تعاملنا مع بياناتك:</p>
          
          <div class="principles-grid">
            <div 
              v-for="principle in principles" 
              :key="principle.id"
              class="principle-card"
            >
              <h4>
                <i :class="`fas fa-${principle.icon}`"></i>
                {{ principle.title }}
              </h4>
              <p>{{ principle.description }}</p>
            </div>
          </div>
        </DataProtectionCard>

        <!-- Protection Methods -->
        <DataProtectionCard 
          :icon="'cogs'"
          :title="'كيف نحمي بياناتك'"
        >
          <p>نطبق مجموعة من الإجراءات التقنية والتنظيمية المتقدمة لحماية بياناتك:</p>
          
          <ul class="protection-list">
            <li v-for="(method, index) in protectionMethods" :key="index">
              {{ method }}
            </li>
          </ul>
          
          <p>نقوم بإجراء اختبارات اختراق دورية للتأكد من متانة أنظمتنا الأمنية ونقاط الضعف المحتملة.</p>
        </DataProtectionCard>

        <!-- Contact Information -->
        <section class="contact-section">
          <h3>لديك استفسارات حول حماية البيانات؟</h3>
          <p>فريق حماية البيانات لدينا مستعد للإجابة على أي أسئلة لديك حول كيفية تعاملنا مع بياناتك الشخصية والذكريات الرقمية.</p>
          
          <div class="contact-grid">
            <ContactItem 
              v-for="contact in contactInfo" 
              :key="contact.id"
              :icon="contact.icon"
              :title="contact.title"
              :content="contact.content"
            />
          </div>
        </section>
      </div>
    </main>

    <!-- Footer -->
    <footer class="app-footer">
      <div class="container">
        <div class="footer-content">
          <div class="footer-logo">
            <i class="fas fa-brain"></i>
            <span>Scene Memory</span>
          </div>
          
          <div class="copyright">
            <p>© {{ currentYear }} Scene Memory. جميع الحقوق محفوظة.</p>
          </div>
          
          <div class="social-links">
            <a 
              v-for="social in socialLinks" 
              :key="social.id"
              :href="social.link"
              :title="social.title"
            >
              <i :class="`fab fa-${social.icon}`"></i>
            </a>
          </div>
        </div>
      </div>
    </footer>
  </div>
</template>

<script>
export default {
  name: 'DataProtectionPage',
  
  components: {
    DataProtectionCard: {
      props: ['icon', 'title'],
      template: `
        <div class="data-protection-card" @mouseenter="hover = true" @mouseleave="hover = false">
          <div class="card-header">
            <div class="card-icon">
              <i :class="'fas fa-' + icon"></i>
            </div>
            <h3>{{ title }}</h3>
          </div>
          <div class="card-content">
            <slot></slot>
          </div>
        </div>
      `,
      data() {
        return {
          hover: false
        }
      },
      computed: {
        cardStyle() {
          return {
            transform: this.hover ? 'translateY(-5px)' : 'translateY(0)',
            boxShadow: this.hover 
              ? '0 12px 20px rgba(0, 0, 0, 0.12)' 
              : '0 6px 15px rgba(0, 0, 0, 0.08)'
          }
        }
      }
    },
    
    ContactItem: {
      props: ['icon', 'title', 'content'],
      template: `
        <div class="contact-item">
          <div class="contact-icon">
            <i :class="'fas fa-' + icon"></i>
          </div>
          <div class="contact-details">
            <strong>{{ title }}</strong>
            <p>{{ content }}</p>
          </div>
        </div>
      `
    }
  },
  
  data() {
    return {
      activeNav: 'data-protection',
      currentYear: new Date().getFullYear(),
      navItems: [
        { id: 'home', text: 'الرئيسية', link: '#' },
        { id: 'about', text: 'عن المشروع', link: '#' },
        { id: 'features', text: 'الميزات', link: '#' },
        { id: 'data-protection', text: 'حماية البيانات', link: '#' },
        { id: 'contact', text: 'اتصل بنا', link: '#' }
      ],
      dataTypes: [
        { 
          id: 1, 
          title: 'البيانات الشخصية', 
          description: 'مثل اسمك، عنوان بريدك الإلكتروني، وصورك الشخصية التي تشاركها طوعًا لربطها بذكرياتك.',
          color: '#1a237e'
        },
        { 
          id: 2, 
          title: 'الذكريات الرقمية', 
          description: 'الصور، الفيديوهات، الملاحظات النصية، والتسجيلات الصوتية التي تحفظها كجزء من ذكرياتك.',
          color: '#4a148c'
        },
        { 
          id: 3, 
          title: 'بيانات الاستخدام', 
          description: 'معلومات حول كيفية تفاعلك مع التطبيق لتحسين تجربتك، دون انتهاك خصوصيتك.',
          color: '#311b92'
        }
      ],
      principles: [
        { 
          id: 1, 
          title: 'الشفافية', 
          icon: 'check-circle',
          description: 'نوضح دائمًا كيفية استخدام بياناتك ونحصل على موافقتك قبل جمع أي معلومات شخصية.'
        },
        { 
          id: 2, 
          title: 'تقليل البيانات', 
          icon: 'briefcase',
          description: 'نجمع فقط البيانات الضرورية لتقديم الخدمة ولا نحتفظ بالمعلومات أكثر من المدة المطلوبة.'
        },
        { 
          id: 3, 
          title: 'التشفير', 
          icon: 'lock',
          description: 'نستخدم تشفيرًا متقدمًا لجميع البيانات المخزنة والمنقولة، بما في ذلك الذكريات الرقمية.'
        },
        { 
          id: 4, 
          title: 'التحكم', 
          icon: 'user-shield',
          description: 'لديك دائمًا التحكم الكامل في بياناتك ويمكنك حذفها أو تصديرها في أي وقت.'
        }
      ],
      protectionMethods: [
        'تخزين البيانات على خوادم آمنة في مراكز بيانات معتمدة مع أنظمة مراقبة مستمرة',
        'استخدام تشفير من طرف إلى طرف (End-to-End Encryption) للذكريات الحساسة',
        'تنفيذ مصادقة متعددة العوامل للوصول إلى الحسابات',
        'مراجعة أمنية منتظمة لأنظمتنا وتطبيقاتنا',
        'تدريب فريقنا على أفضل ممارسات أمن وحماية البيانات',
        'تحديث أنظمتنا باستمرار لمواجهة التهديدات الأمنية الحديثة'
      ],
      contactInfo: [
        { 
          id: 1, 
          icon: 'envelope', 
          title: 'البريد الإلكتروني', 
          content: 'privacy@scenememory.com' 
        },
        { 
          id: 2, 
          icon: 'phone', 
          title: 'هاتف الدعم', 
          content: '+966 123 456 789' 
        },
        { 
          id: 3, 
          icon: 'clock', 
          title: 'ساعات العمل', 
          content: 'الأحد - الخميس: 9 صباحًا - 5 مساءً' 
        }
      ],
      socialLinks: [
        { id: 1, icon: 'twitter', title: 'تويتر', link: '#' },
        { id: 2, icon: 'facebook', title: 'فيسبوك', link: '#' },
        { id: 3, icon: 'linkedin', title: 'لينكدإن', link: '#' },
        { id: 4, icon: 'instagram', title: 'انستغرام', link: '#' }
      ]
    }
  },
  
  methods: {
    setActiveNav(navId) {
      this.activeNav = navId;
      // هنا يمكن إضافة منطق التنقل بين الصفحات
      console.log(`تم النقر على: ${navId}`);
    }
  },
  
  mounted() {
    console.log('صفحة حماية البيانات جاهزة');
  }
}
</script>

<style scoped>
/* CSS Styles */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.data-protection-page {
  background-color: #f8f9fa;
  color: #333;
  line-height: 1.6;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 20px;
}

/* Header */
.app-header {
  background: linear-gradient(135deg, #1a237e 0%, #4a148c 100%);
  color: white;
  padding: 25px 0;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  position: relative;
  overflow: hidden;
}

.app-header::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path d="M0,0 L100,0 L100,100 Z" fill="rgba(255,255,255,0.05)"/></svg>');
  background-size: cover;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: relative;
  z-index: 2;
}

.logo {
  display: flex;
  align-items: center;
  gap: 15px;
}

.logo-icon {
  font-size: 2.5rem;
  color: #bb86fc;
}

.logo-text h1 {
  font-size: 1.8rem;
  margin-bottom: 5px;
}

.logo-text p {
  font-size: 0.9rem;
  opacity: 0.9;
}

.main-nav ul {
  display: flex;
  list-style: none;
  gap: 25px;
}

.main-nav a {
  color: white;
  text-decoration: none;
  font-weight: 500;
  transition: color 0.3s;
  padding: 5px 0;
  position: relative;
  cursor: pointer;
}

.main-nav a:hover {
  color: #bb86fc;
}

.main-nav a.active {
  color: #bb86fc;
}

.main-nav a.active::after {
  content: '';
  position: absolute;
  bottom: 0;
  right: 0;
  width: 100%;
  height: 2px;
  background-color: #bb86fc;
}

/* Main Content */
.main-content {
  padding-bottom: 40px;
}

.page-header {
  text-align: center;
  padding: 50px 0 30px;
}

.page-header h2 {
  font-size: 2.5rem;
  color: #1a237e;
  margin-bottom: 15px;
}

.page-header p {
  font-size: 1.1rem;
  color: #666;
  max-width: 700px;
  margin: 0 auto;
}

/* Data Protection Card */
.data-protection-card {
  background-color: white;
  border-radius: 12px;
  box-shadow: 0 6px 15px rgba(0, 0, 0, 0.08);
  padding: 40px;
  margin-bottom: 40px;
  transition: transform 0.3s, box-shadow 0.3s;
}

.card-header {
  display: flex;
  align-items: center;
  margin-bottom: 25px;
  padding-bottom: 15px;
  border-bottom: 1px solid #eee;
}

.card-icon {
  background-color: #e8eaf6;
  width: 60px;
  height: 60px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-left: 20px;
}

.card-icon i {
  font-size: 1.8rem;
  color: #1a237e;
}

.card-header h3 {
  font-size: 1.6rem;
  color: #1a237e;
}

.card-content p {
  margin-bottom: 20px;
  font-size: 1.05rem;
  color: #444;
}

/* Data Types Grid */
.data-types-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 25px;
  margin-top: 30px;
}

.data-type-card {
  background-color: #f5f5f5;
  border-radius: 10px;
  padding: 25px;
  border-right: 4px solid;
  transition: transform 0.3s;
}

.data-type-card:hover {
  transform: translateY(-3px);
}

.data-type-card h4 {
  color: #1a237e;
  margin-bottom: 15px;
  font-size: 1.3rem;
}

/* Principles Grid */
.principles-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 20px;
  margin-top: 30px;
}

.principle-card {
  background-color: #f8f9ff;
  border-radius: 10px;
  padding: 25px;
  border-top: 4px solid #bb86fc;
  transition: transform 0.3s;
}

.principle-card:hover {
  transform: translateY(-3px);
}

.principle-card h4 {
  color: #4a148c;
  margin-bottom: 15px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.principle-card i {
  color: #bb86fc;
}

/* Protection List */
.protection-list {
  margin: 20px 0;
  padding-right: 20px;
}

.protection-list li {
  margin-bottom: 12px;
  position: relative;
  padding-right: 25px;
  color: #444;
}

.protection-list li::before {
  content: '✓';
  position: absolute;
  right: 0;
  top: 0;
  color: #4caf50;
  font-weight: bold;
}

/* Contact Section */
.contact-section {
  background: linear-gradient(135deg, #e8eaf6 0%, #f3e5f5 100%);
  border-radius: 12px;
  padding: 40px;
  margin-top: 50px;
  text-align: center;
}

.contact-section h3 {
  color: #1a237e;
  margin-bottom: 25px;
  font-size: 1.8rem;
}

.contact-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 30px;
  margin-top: 30px;
}

.contact-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 15px;
  background-color: white;
  padding: 25px;
  border-radius: 10px;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
}

.contact-icon {
  width: 50px;
  height: 50px;
  background-color: #1a237e;
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.3rem;
}

.contact-details strong {
  display: block;
  margin-bottom: 8px;
  color: #1a237e;
}

/* Footer */
.app-footer {
  background-color: #1a237e;
  color: white;
  padding: 30px 0;
  margin-top: 60px;
}

.footer-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 20px;
}

.footer-logo {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 1.2rem;
  font-weight: 600;
}

.copyright {
  opacity: 0.8;
}

.social-links {
  display: flex;
  gap: 15px;
}

.social-links a {
  color: white;
  font-size: 1.2rem;
  transition: color 0.3s;
  text-decoration: none;
}

.social-links a:hover {
  color: #30A8FF;
}

.data-protection-page {
  background:
    radial-gradient(circle at top left, rgba(48, 168, 255, 0.10), transparent 32rem),
    linear-gradient(180deg, #FFFFFF, #F8FAFC);
}

.data-protection-page .app-header,
.data-protection-page .data-protection-card,
.data-protection-page .info-card,
.data-protection-page .contact-card {
  border: 1px solid #E5EDF6;
  border-radius: 24px;
  background: #FFFFFF;
  box-shadow: 0 10px 35px rgba(13, 77, 151, 0.06);
}

.data-protection-page h1,
.data-protection-page h2,
.data-protection-page h3 {
  color: #06142A;
}

.data-protection-page .main-nav a.active,
.data-protection-page .main-nav a:hover {
  color: #0D4D97;
  background: #EAF4FF;
}

.data-protection-page .card-icon,
.data-protection-page .logo-icon {
  background: linear-gradient(135deg, #0D4D97, #1677FF);
}

/* Responsive */
@media (max-width: 992px) {
  .header-content {
    flex-direction: column;
    gap: 20px;
  }
  
  .main-nav ul {
    flex-wrap: wrap;
    justify-content: center;
  }
}

@media (max-width: 768px) {
  .page-header h2 {
    font-size: 2rem;
  }
  
  .data-protection-card {
    padding: 25px;
  }
  
  .card-header {
    flex-direction: column;
    text-align: center;
    gap: 15px;
  }
  
  .card-icon {
    margin-left: 0;
  }
  
  .data-types-grid,
  .principles-grid,
  .contact-grid {
    grid-template-columns: 1fr;
  }
  
  .footer-content {
    flex-direction: column;
    text-align: center;
  }
}
</style>
