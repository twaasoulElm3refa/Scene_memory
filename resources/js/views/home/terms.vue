<template>
  <div class="terms-page">
    <!-- Main Content -->
    <main class="main-content">
      <div class="container">
        <!-- Page Header -->
        <section class="page-header">
          <h2>الشروط والأحكام</h2>
          <p>يرجى قراءة شروط وأحكام استخدام Scene Memory بعناية قبل استخدام الخدمة. استخدامك للخدمة يعني موافقتك على هذه الشروط.</p>
          <div class="last-updated">
            <i class="fas fa-calendar-alt"></i>
            آخر تحديث: {{ lastUpdated }}
          </div>
        </section>

        <!-- Table of Contents -->
        <section class="toc-section" v-if="showTOC">
          <div class="toc-header" @click="toggleTOC">
            <h3><i class="fas fa-list"></i> جدول المحتويات</h3>
            <i class="fas" :class="tocExpanded ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
          </div>
          <div class="toc-content" v-if="tocExpanded">
            <ul>
              <li v-for="section in termsSections" :key="section.id">
                <a href="javascript:void(0)" @click="scrollToSection(section.id)">
                  {{ section.title }}
                </a>
              </li>
            </ul>
          </div>
        </section>

        <!-- Terms Sections -->
        <section class="terms-container">
          <div 
            v-for="section in termsSections" 
            :key="section.id"
            :id="'section-' + section.id"
            class="term-section"
          >
            <div class="section-header">
              <h3>
                <span class="section-number">{{ section.id }}.</span>
                {{ section.title }}
              </h3>
              <button 
                v-if="section.details" 
                class="expand-btn"
                @click="toggleSection(section.id)"
                :aria-expanded="expandedSections[section.id]"
              >
                <i class="fas" :class="expandedSections[section.id] ? 'fa-minus' : 'fa-plus'"></i>
              </button>
            </div>
            
            <div class="section-content" v-html="section.content"></div>
            
            <div 
              v-if="section.details && expandedSections[section.id]" 
              class="section-details"
            >
              <div v-for="(detail, index) in section.details" :key="index" class="detail-item">
                <h4 v-if="detail.title">{{ detail.title }}</h4>
                <div v-html="detail.content"></div>
              </div>
            </div>
          </div>
        </section>

        <!-- Acceptance Section -->
        <section class="acceptance-section">
          <div class="acceptance-card">
            <div class="acceptance-icon">
              <i class="fas fa-file-contract"></i>
            </div>
            <div class="acceptance-content">
              <h3>قبول الشروط والأحكام</h3>
              <p>باستخدامك لخدمة Scene Memory، فإنك توافق على الالتزام بهذه الشروط والأحكام. إذا كنت لا توافق على أي جزء من هذه الشروط، يرجى عدم استخدام الخدمة.</p>
              
              <div class="acceptance-actions">
                <label class="acceptance-checkbox">
                  <input 
                    type="checkbox" 
                    v-model="termsAccepted"
                    @change="updateAcceptance"
                  >
                  <span class="checkmark"></span>
                  <span class="checkbox-text">أقر بأنني قد قرأت وفهمت وأوافق على شروط وأحكام Scene Memory</span>
                </label>
                
                <div class="button-group">
                  <button 
                    class="btn btn-primary"
                    :disabled="!termsAccepted"
                    @click="continueToService"
                  >
                    <i class="fas fa-check-circle"></i>
                    أوافق وأستمر
                  </button>
                  <button class="btn btn-secondary" @click="downloadTerms">
                    <i class="fas fa-download"></i>
                    تحميل النسخة PDF
                  </button>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- Contact for Questions -->
        <section class="questions-section">
          <div class="questions-card">
            <h3><i class="fas fa-question-circle"></i> لديك أسئلة؟</h3>
            <p>إذا كان لديك أي استفسارات حول شروط وأحكام الاستخدام، لا تتردد في الاتصال بنا:</p>
            
            <div class="contact-options">
              <div class="contact-option">
                <i class="fas fa-envelope"></i>
                <div>
                  <strong>البريد الإلكتروني</strong>
                  <a href="mailto:legal@scenememory.com">legal@scenememory.com</a>
                </div>
              </div>
              
              <div class="contact-option">
                <i class="fas fa-phone"></i>
                <div>
                  <strong>هاتف الدعم القانوني</strong>
                  <a href="tel:+966123456789">+966 123 456 789</a>
                </div>
              </div>
              
              <div class="contact-option">
                <i class="fas fa-clock"></i>
                <div>
                  <strong>ساعات العمل القانونية</strong>
                  <span>الأحد - الخميس: 10 صباحًا - 4 مساءً</span>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>
    </main>
  </div>
</template>

<script>
export default {
  name: 'TermsPage',
  
  data() {
    return {
      activeNav: 'terms',
      lastUpdated: '1 يناير 2024',
      currentYear: new Date().getFullYear(),
      appVersion: '2.1.0',
      termsAccepted: false,
      tocExpanded: true,
      showTOC: true,
      expandedSections: {},
      
      navItems: [
        { id: 'home', text: 'الرئيسية', link: '#' },
        { id: 'about', text: 'عن المشروع', link: '#' },
        { id: 'features', text: 'الميزات', link: '#' },
        { id: 'privacy', text: 'الخصوصية', link: '#' },
        { id: 'terms', text: 'الشروط', link: '#' },
        { id: 'contact', text: 'اتصل بنا', link: '#' }
      ],
      
      termsSections: [
        {
          id: 1,
          title: 'القبول والشروط',
          content: `
            <p>باستخدامك لخدمة Scene Memory (الخدمة)، فإنك توافق على الالتزام بهذه الشروط والأحكام (الاتفاقية). إذا كنت لا توافق على هذه الشروط، يرجى عدم استخدام الخدمة.</p>
            <p>نحن نحتفظ بالحق في تعديل هذه الشروط في أي وقت. سيتم إعلامك بالتغييرات عبر البريد الإلكتروني أو عبر الإعلان في التطبيق.</p>
          `,
          details: [
            {
              title: 'الأهلية',
              content: `
                <p>يجب أن يكون عمرك 18 عامًا على الأقل لاستخدام هذه الخدمة. باستخدام الخدمة، فإنك تؤكد وتضمن أنك:</p>
                <ul>
                  <li>تبلغ من العمر 18 عامًا أو أكثر</li>
                  <li>لديك السلطة القانونية للقبول بهذه الشروط</li>
                  <li>ستستخدم الخدمة وفقًا للقوانين واللوائح المعمول بها</li>
                </ul>
              `
            }
          ]
        },
        {
          id: 2,
          title: 'حساب المستخدم',
          content: `
            <p>للاستفادة الكاملة من الخدمة، يجب عليك إنشاء حساب. أنت مسؤول عن:</p>
          `,
          details: [
            {
              title: 'إنشاء الحساب',
              content: `
                <ul>
                  <li>تقديم معلومات دقيقة وحديثة وكاملة</li>
                  <li>الحفاظ على سرية معلومات تسجيل الدخول الخاصة بك</li>
                  <li>عدم مشاركة حسابك مع أي شخص آخر</li>
                  <li>إبلاغنا فورًا عن أي استخدام غير مصرح لحسابك</li>
                </ul>
              `
            },
            {
              title: 'إغلاق الحساب',
              content: `
                <p>يمكنك إغلاق حسابك في أي وقت عبر إعدادات الحساب. عند الإغلاق:</p>
                <ul>
                  <li>سيتم حذف بياناتك الشخصية وفقًا لسياسة الخصوصية</li>
                  <li>قد يتم الاحتفاظ ببعض البيانات لأغراض قانونية أو تنظيمية</li>
                  <li>لن تتمكن من الوصول إلى الخدمات المميزة</li>
                </ul>
              `
            }
          ]
        },
        {
          id: 3,
          title: 'الاشتراكات والدفع',
          content: `
            <p>تقدم Scene Memory خطط اشتراك متنوعة. تتضمن بعض الميزات رسوم اشتراك.</p>
          `,
          details: [
            {
              title: 'تجديد الاشتراكات',
              content: `
                <p>يتم تجديد الاشتراكات تلقائيًا في نهاية كل فترة اشتراك ما لم تقم بإلغاء الاشتراك قبل 24 ساعة على الأقل من تاريخ التجديد.</p>
              `
            },
            {
              title: 'المبالغ المستردة',
              content: `
                <p>نحن نقدم ضمان استرداد الأموال لمدة 14 يومًا للاشتراكات الجديدة. بعد هذه الفترة، لا يمكن استرداد المبالغ المدفوعة.</p>
              `
            }
          ]
        },
        {
          id: 4,
          title: 'حقوق الملكية الفكرية',
          content: `
            <p>جميع حقوق الملكية الفكرية في الخدمة ومحتواها هي ملك لنا أو للمرخص لنا.</p>
          `,
          details: [
            {
              title: 'حقوقك',
              content: `
                <p>أنت تحتفظ بجميع حقوق الملكية الفكرية في المحتوى الذي تنشره على الخدمة. من خلال النشر، تمنحنا ترخيصًا غير حصري وقابل للنقل للاستخدام والتخزين وعرض وتعديل هذا المحتوى لتقديم الخدمة.</p>
              `
            },
            {
              title: 'قيود الاستخدام',
              content: `
                <p>لا يجوز لك:</p>
                <ul>
                  <li>نسخ أو تعديل أو توزيع أي جزء من الخدمة دون إذن كتابي</li>
                  <li>استخدام الخدمة لأي غرض غير قانوني أو غير مصرح به</li>
                  <li>محاولة اختراق أو تعطيل الخدمة</li>
                </ul>
              `
            }
          ]
        },
        {
          id: 5,
          title: 'المحتوى والمسؤولية',
          content: `
            <p>أنت مسؤول عن جميع المحتوى الذي تنشره على الخدمة. يجب أن لا يحتوي المحتوى على:</p>
          `,
          details: [
            {
              title: 'المحتوى المحظور',
              content: `
                <ul>
                  <li>مواد غير قانونية أو ضارة أو تهديدية أو مسيئة</li>
                  <li>مواد تهدد الأمن القومي أو تنتهك القوانين المحلية</li>
                  <li>محتوى يحمي حقوق النشر أو الملكية الفكرية للآخرين</li>
                  <li>فيروسات أو برامج ضارة</li>
                  <li>محتوى غير لائق أو إباحي</li>
                </ul>
              `
            },
            {
              title: 'مراجعة المحتوى',
              content: `
                <p>نحتفظ بالحق في مراجعة وإزالة أي محتوى نعتقد أنه ينتهك هذه الشروط دون إشعار مسبق.</p>
              `
            }
          ]
        },
        {
          id: 6,
          title: 'الضمان والمسؤولية',
          content: `
            <p>تقدم الخدمة "كما هي" و "كما هي متاحة". نحن لا نضمن أن الخدمة ستكون متاحة دائمًا أو خالية من الأخطاء.</p>
          `,
          details: [
            {
              title: 'تحديد المسؤولية',
              content: `
                <p>في أقصى حد يسمح به القانون، لن نكون مسؤولين عن:</p>
                <ul>
                  <li>أي أضرار غير مباشرة أو تبعية أو عرضية</li>
                  <li>فقدان البيانات أو الذكريات المخزنة</li>
                  <li>أي أضرار ناتجة عن استخدام أو عدم القدرة على استخدام الخدمة</li>
                </ul>
              `
            }
          ]
        },
        {
          id: 7,
          title: 'الإنهاء',
          content: `
            <p>قد ننهي أو نعلق وصولك إلى الخدمة فورًا دون إشعار مسبق إذا انتهكت هذه الشروط.</p>
          `
        },
        {
          id: 8,
          title: 'القانون الحاكم',
          content: `
            <p>تخضع هذه الشروط وتفسر وفقًا لقوانين المملكة العربية السعودية. أي نزاعات تنشأ عن هذه الشروط ستخضع للاختصاص الحصري لمحاكم المملكة العربية السعودية.</p>
          `
        },
        {
          id: 9,
          title: 'التغييرات على الشروط',
          content: `
            <p>نحتفظ بالحق في تعديل هذه الشروط في أي وقت. سيتم نشر التغييرات على هذه الصفحة وسنخطرك عبر البريد الإلكتروني إذا كانت التغييرات جوهرية.</p>
            <p>استمرارك في استخدام الخدمة بعد التغييرات يعني موافقتك على الشروط المعدلة.</p>
          `
        }
      ]
    }
  },
  
  mounted() {
    // توسيع القسم الأول تلقائيًا
    if (this.termsSections.length > 0) {
      this.expandedSections[this.termsSections[0].id] = true;
    }
    
    // التحقق إذا كان المستخدم قد وافق مسبقًا على الشروط
    const accepted = localStorage.getItem('termsAccepted');
    if (accepted === 'true') {
      this.termsAccepted = true;
    }
  },
  
  methods: {
    setActiveNav(navId) {
      this.activeNav = navId;
      // في تطبيق حقيقي، هذا سينتقل للصفحة المناسبة
      console.log(`تم النقر على: ${navId}`);
    },
    
    toggleTOC() {
      this.tocExpanded = !this.tocExpanded;
    },
    
    toggleSection(sectionId) {
      this.$set(this.expandedSections, sectionId, !this.expandedSections[sectionId]);
    },
    
    scrollToSection(sectionId) {
      const element = document.getElementById(`section-${sectionId}`);
      if (element) {
        element.scrollIntoView({ behavior: 'smooth' });
        this.expandedSections[sectionId] = true;
      }
    },
    
    updateAcceptance() {
      localStorage.setItem('termsAccepted', this.termsAccepted);
    },
    
    continueToService() {
      if (this.termsAccepted) {
        // في تطبيق حقيقي، هذا سيوجه المستخدم للخدمة
        alert('شكرًا لقبولك الشروط والأحكام. يتم توجيهك الآن للخدمة...');
        
        // تخزين القبول
        localStorage.setItem('termsAcceptedDate', new Date().toISOString());
        localStorage.setItem('termsAccepted', 'true');
        localStorage.setItem('termsVersion', this.appVersion);
        
        // توجيه المستخدم (في تطبيق حقيقي)
        // this.$router.push('/dashboard');
      }
    },
    
    downloadTerms() {
      // في تطبيق حقيقي، هذا سيحمل ملف PDF
      alert('جاري تحميل نسخة PDF من الشروط والأحكام...');
      
      // محاكاة التحميل
      const link = document.createElement('a');
      link.href = '#';
      link.download = `Scene-Memory-Terms-${this.lastUpdated}.pdf`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }
  },
  
  computed: {
    acceptanceStatus() {
      return this.termsAccepted ? 'موافق' : 'غير موافق';
    }
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

.terms-page {
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
  position: sticky;
  top: 0;
  z-index: 100;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
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
  padding: 40px 0;
}

.page-header {
  text-align: center;
  padding: 30px 0;
  margin-bottom: 30px;
}

.page-header h2 {
  font-size: 2.5rem;
  color: #1a237e;
  margin-bottom: 15px;
}

.page-header p {
  font-size: 1.1rem;
  color: #666;
  max-width: 800px;
  margin: 0 auto 15px;
}

.last-updated {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background-color: #e8eaf6;
  padding: 8px 16px;
  border-radius: 20px;
  color: #1a237e;
  font-size: 0.9rem;
}

.last-updated i {
  color: #4a148c;
}

/* Table of Contents */
.toc-section {
  background-color: white;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  margin-bottom: 30px;
  overflow: hidden;
}

.toc-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 25px;
  background-color: #f5f5f5;
  cursor: pointer;
  transition: background-color 0.3s;
}

.toc-header:hover {
  background-color: #eeeeee;
}

.toc-header h3 {
  color: #1a237e;
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0;
}

.toc-content {
  padding: 20px 25px;
  border-top: 1px solid #eee;
}

.toc-content ul {
  list-style: none;
  columns: 2;
  gap: 20px;
}

.toc-content li {
  margin-bottom: 12px;
  break-inside: avoid;
}

.toc-content a {
  color: #4a148c;
  text-decoration: none;
  transition: color 0.3s;
  padding: 5px 0;
  display: block;
}

.toc-content a:hover {
  color: #1a237e;
  text-decoration: underline;
}

/* Terms Sections */
.terms-container {
  margin-bottom: 40px;
}

.term-section {
  background-color: white;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  margin-bottom: 25px;
  overflow: hidden;
  transition: transform 0.3s;
}

.term-section:hover {
  transform: translateY(-3px);
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 25px 30px;
  background-color: #f8f9ff;
  border-bottom: 1px solid #e8eaf6;
}

.section-header h3 {
  color: #1a237e;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 10px;
}

.section-number {
  background-color: #1a237e;
  color: white;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.9rem;
}

.expand-btn {
  background: none;
  border: none;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #4a148c;
  font-size: 1.1rem;
  transition: background-color 0.3s;
}

.expand-btn:hover {
  background-color: #e8eaf6;
}

.section-content {
  padding: 25px 30px;
}

.section-content p {
  margin-bottom: 15px;
  color: #444;
}

.section-content ul {
  margin: 15px 0;
  padding-right: 20px;
}

.section-content li {
  margin-bottom: 10px;
  position: relative;
  padding-right: 15px;
}

.section-content li::before {
  content: '•';
  position: absolute;
  right: 0;
  color: #4a148c;
  font-weight: bold;
}

.section-details {
  padding: 0 30px 25px;
  border-top: 1px solid #eee;
  margin-top: 15px;
}

.detail-item {
  margin-bottom: 25px;
  background-color: #f9f9f9;
  padding: 20px;
  border-radius: 8px;
}

.detail-item h4 {
  color: #4a148c;
  margin-bottom: 15px;
  padding-bottom: 10px;
  border-bottom: 1px solid #e8eaf6;
}

/* Acceptance Section */
.acceptance-section {
  margin: 50px 0;
}

.acceptance-card {
  background: linear-gradient(135deg, #e8eaf6 0%, #f3e5f5 100%);
  border-radius: 12px;
  padding: 40px;
  display: flex;
  gap: 30px;
  align-items: flex-start;
}

.acceptance-icon {
  background-color: white;
  width: 70px;
  height: 70px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  color: #1a237e;
  flex-shrink: 0;
}

.acceptance-content {
  flex: 1;
}

.acceptance-content h3 {
  color: #1a237e;
  margin-bottom: 20px;
  font-size: 1.8rem;
}

.acceptance-actions {
  margin-top: 25px;
}

.acceptance-checkbox {
  display: flex;
  align-items: flex-start;
  gap: 15px;
  margin-bottom: 25px;
  cursor: pointer;
  position: relative;
  padding-right: 35px;
}

.acceptance-checkbox input {
  position: absolute;
  opacity: 0;
  cursor: pointer;
  height: 0;
  width: 0;
}

.checkmark {
  position: absolute;
  right: 0;
  top: 0;
  height: 25px;
  width: 25px;
  background-color: white;
  border: 2px solid #ccc;
  border-radius: 4px;
  transition: all 0.3s;
}

.acceptance-checkbox:hover input ~ .checkmark {
  border-color: #1a237e;
}

.acceptance-checkbox input:checked ~ .checkmark {
  background-color: #1a237e;
  border-color: #1a237e;
}

.checkmark:after {
  content: "";
  position: absolute;
  display: none;
}

.acceptance-checkbox input:checked ~ .checkmark:after {
  display: block;
}

.acceptance-checkbox .checkmark:after {
  right: 7px;
  top: 3px;
  width: 7px;
  height: 12px;
  border: solid white;
  border-width: 0 3px 3px 0;
  transform: rotate(45deg);
}

.checkbox-text {
  font-weight: 500;
  color: #333;
  line-height: 1.5;
}

.button-group {
  display: flex;
  gap: 15px;
  flex-wrap: wrap;
}

.btn {
  padding: 12px 24px;
  border-radius: 6px;
  border: none;
  font-weight: 600;
  font-size: 1rem;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: all 0.3s;
}

.btn-primary {
  background-color: #1a237e;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background-color: #0d153c;
  transform: translateY(-2px);
}

.btn-primary:disabled {
  background-color: #9fa8da;
  cursor: not-allowed;
  transform: none;
}

.btn-secondary {
  background-color: white;
  color: #1a237e;
  border: 2px solid #1a237e;
}

.btn-secondary:hover {
  background-color: #f5f5f5;
  transform: translateY(-2px);
}

/* Questions Section */
.questions-section {
  margin-top: 40px;
}

.questions-card {
  background-color: white;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  padding: 40px;
}

.questions-card h3 {
  color: #1a237e;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 1.8rem;
}

.questions-card p {
  margin-bottom: 30px;
  color: #666;
  font-size: 1.05rem;
}

.contact-options {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 25px;
}

.contact-option {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 20px;
  background-color: #f8f9ff;
  border-radius: 8px;
  border-right: 4px solid #bb86fc;
}

.contact-option i {
  font-size: 1.5rem;
  color: #4a148c;
  width: 40px;
  text-align: center;
}

.contact-option strong {
  display: block;
  color: #1a237e;
  margin-bottom: 5px;
}

.contact-option a, .contact-option span {
  color: #666;
  text-decoration: none;
  transition: color 0.3s;
}

.contact-option a:hover {
  color: #1a237e;
  text-decoration: underline;
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

.footer-links {
  display: flex;
  gap: 20px;
}

.footer-links a {
  color: white;
  text-decoration: none;
  transition: color 0.3s;
}

.footer-links a:hover {
  color: #bb86fc;
  text-decoration: underline;
}

.copyright {
  text-align: center;
}

.copyright p {
  margin-bottom: 5px;
}

.version {
  font-size: 0.9rem;
  opacity: 0.8;
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
  
  .toc-content ul {
    columns: 1;
  }
  
  .acceptance-card {
    flex-direction: column;
    align-items: center;
    text-align: center;
  }
}

@media (max-width: 768px) {
  .page-header h2 {
    font-size: 2rem;
  }
  
  .section-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 15px;
  }
  
  .expand-btn {
    align-self: flex-end;
  }
  
  .contact-options {
    grid-template-columns: 1fr;
  }
  
  .footer-content {
    flex-direction: column;
    text-align: center;
  }
  
  .footer-links {
    flex-direction: column;
    gap: 10px;
  }
  
  .button-group {
    flex-direction: column;
  }
  
  .btn {
    width: 100%;
    justify-content: center;
  }
}
</style>