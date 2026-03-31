<template>
    <div class="min-vh-100 d-flex align-items-center justify-content-center position-relative overflow-hidden">
        <!-- Animated wavy background overlay -->
        <div class="position-absolute w-100 h-100 waves"></div>

        <!-- Main content wrapper -->
        <div class="auth-container position-relative z-2 d-flex flex-column flex-lg-row w-100 h-100">
            <!-- Left branding side -->
            <div class="d-none d-lg-flex col-lg-5 align-items-center justify-content-center p-5 position-relative">
                <div class="text-center">
                    <img src="/images/event_logo.png" alt="NEXTLEVEL Logo" class="height-auto logo-glow"
                        style="width: auto" />
                    <h1 class="display-5 fw-black mb-3">Scene Memory</h1>
                    <p class="lead fs-3 fw-medium opacity-90">Share your memories with the world</p>
                </div>
            </div>

            <!-- Form side -->
            <div class="col-12 col-lg-7 d-flex align-items-center justify-content-center p-4 p-md-5">
                <div class="glass-card rounded-4 shadow-glow p-4 p-md-5 w-100"
                    style="max-width: 480px; backdrop-filter: blur(16px)">
                    <!-- Tabs with animated underline -->
                    <div class="position-relative mb-4"
                        style="border-bottom: 1px solid rgba(255, 255, 255, 0.15); max-width: 400px">
                        <ul class="nav nav-pills nav-fill">
                            <li class="nav-item text-center flex-grow-1">
                                <button class="nav-link px-0 fs-5 fw-semibold position-relative"
                                    :class="{ 'active-tab': tab === 'login' }" @click.prevent="tab = 'login'">
                                    Login
                                </button>
                            </li>

                            <li class="nav-item text-center flex-grow-1">
                                <button class="nav-link px-0 fs-5 fw-semibold position-relative"
                                    :class="{ 'active-tab': tab === 'register' }" @click.prevent="tab = 'register'">
                                    Register
                                </button>
                            </li>
                        </ul>

                        <!-- Animated underline -->
                        <div class="position-absolute rounded-pill"
                            style="height: 3px; bottom: 0; transition: all 0.4s ease"></div>
                    </div>

                    <!-- Success Message -->
                    <transition name="fade">
                        <p v-if="successMessage" class="text-center text-success mb-4">
                            {{ successMessage }}
                        </p>
                    </transition>

                    <!-- Login Form -->
                    <form v-if="tab === 'login'" @submit.prevent="handleLogin" class="d-flex flex-column gap-4">
                        <input v-model="loginForm.email" type="email"
                            class="form-control form-control-lg glass-input rounded-3 py-3 px-4 fs-5"
                            placeholder="Email Address" required />

                        <div class="input-group input-group-lg">
                            <input v-model="loginForm.password" :type="showPassword ? 'text' : 'password'"
                                class="form-control glass-input rounded-3 py-3 px-4 fs-5 border-end-0"
                                placeholder="Password" required />
                            <span class="input-group-text glass-input rounded-3 border-start-0"
                                @click="showPassword = !showPassword">
                                <span class="fs-4">{{ showPassword ? "👁️" : "👁️‍🗨️" }}</span>
                            </span>
                        </div>

                        <button type="submit" class="btn btn-glow btn-lg rounded-3 py-3 fw-bold fs-5 mt-3"
                            :disabled="loading">
                            {{ loading ? "Loading..." : "Login" }}
                        </button>
                        <p class="text-right">
                            <a href="#" class="text-glow fw-medium fs-6" @click.prevent="tab = 'forgot'">
                                هل نسيت كلمة المرور؟
                            </a>
                        </p>

                        <p v-if="error" class="text-center text-danger text-sm">{{ error }}</p>

                        <!-- Google Login Button -->
                        <div class="d-flex flex-column gap-3">
                            <button @click.prevent="handleGoogleLogin"
                                class="btn btn-google btn-lg d-flex align-items-center justify-content-center gap-2">
                                <img src="/images/google_logo.png" alt="Google Logo"
                                    style="width: 65px; height: 36px" />
                                Continue with Google
                            </button>
                        </div>
                         <!-- Apple Login Button -->
                        <!-- <div class="d-flex flex-column gap-3 mt-3">
                            <button @click.prevent="handleAppleLogin"
                                class="btn btn-apple btn-lg d-flex align-items-center justify-content-center gap-2">
                                <img src="/images/apple_logo.png" alt="Apple Logo" style="width: 36px; height: 36px" />
                                Continue with Apple
                            </button>
                        </div> -->
                        <!-- Facebook Login Button -->
                        <!-- <div class="d-flex flex-column gap-3 mt-3">
                            <button @click.prevent="handleFacebookLogin"
                                class="btn btn-facebook btn-lg d-flex align-items-center justify-content-center gap-2">
                                <img src="/images/facebook_logo.png" alt="Facebook Logo"
                                    style="width: 36px; height: 36px" />
                                Continue with Facebook
                            </button>
                        </div> -->
                    </form>

                    <!-- Register Form -->
                    <form v-else @submit.prevent="handleRegister" class="d-flex flex-column gap-4">
                        <input v-model="registerForm.name" type="text"
                            class="form-control form-control-lg glass-input rounded-3 px-4 fs-5" placeholder="Full Name"
                            required />
                        <input v-model="registerForm.email" type="email"
                            class="form-control form-control-lg glass-input rounded-3 px-4 fs-5"
                            placeholder="Email Address" required />

                        <!-- ┌─────────────────────── Position ───────────────────────┐ -->
                        <input v-model="registerForm.position" type="text"
                            class="form-control form-control-lg glass-input rounded-3 px-4 fs-5"
                            placeholder="Position / Job Title (optional)" required />

                        <!-- ┌──────────────────── Date of Birth ─────────────────────┐ -->
                        <div class="form-floating">
                            <input v-model="registerForm.date_of_birth" type="date" class="form-control glass-input"
                                id="date_of_birth" placeholder="Date of Birth" required />
                            <label for="date_of_birth" class="text-black">تاريخ الميلاد</label>
                        </div>

                        <!-- Country Live Search -->
                        <div class="position-relative">
                            <input v-model="registerForm.country" list="country-list" type="text"
                                class="form-control form-control-lg glass-input rounded-3 px-4 fs-5"
                                placeholder="الدولة (اكتب للبحث...)" required autocomplete="off" />
                            <datalist id="country-list">
                                <option v-for="country in countries" :key="country.code" :value="country.name">
                                    {{ country.name }} • {{ country.en }}
                                </option>
                            </datalist>
                        </div>
                        <div class="input-group input-group-lg">
                            <input v-model="registerForm.password" :type="showPassword ? 'text' : 'password'"
                                class="form-control glass-input rounded-3 px-4 fs-5 border-end-0" placeholder="Password"
                                required />
                            <span class="input-group-text glass-input rounded-3 border-start-0"
                                @click="showPassword = !showPassword">
                                <span class="fs-4">{{ showPassword ? "👁️‍🗨️" : "👁️" }}</span>
                            </span>
                        </div>

                        <div class="input-group input-group-lg">
                            <input v-model="registerForm.password_confirmation"
                                :type="showPassword ? 'text' : 'password'"
                                class="form-control glass-input rounded-3 px-4 fs-5 border-end-0"
                                placeholder="Confirm Password" required />
                            <span class="input-group-text glass-input rounded-3 border-start-0"
                                @click="showPassword = !showPassword">
                                <span class="fs-4">{{ showPassword ? "👁️‍🗨️" : "👁️" }}</span>
                            </span>
                        </div>
                        <button type="submit" class="btn btn-glow btn-lg rounded-3 py-2 fw-bold fs-5 mt-3"
                            :disabled="loading">
                            {{ loading ? "Creating..." : "Create Account" }}
                        </button>

                        <p v-if="error" class="text-center text-danger text-sm mt-2">{{ error }}</p>

                    </form>

                    <form v-if="tab === 'forgot'" @submit.prevent="handleForgot" class="d-flex flex-column gap-4">
                        <input v-model="forgotEmail" type="email"
                            class="form-control form-control-lg glass-input rounded-3 py-3 px-4 fs-5"
                            placeholder="Email Address" required />

                        <button class="btn btn-glow btn-lg rounded-3 py-3 fw-bold fs-5">
                            إرسال الكود
                        </button>

                        <p class="text-center">
                            <a href="#" class="text-glow" @click.prevent="tab = 'login'">
                                رجوع لتسجيل الدخول
                            </a>
                        </p>
                    </form>

                    <form v-if="tab === 'reset'" @submit.prevent="handleReset" class="d-flex flex-column gap-4">
                        <input v-model="resetForm.email" type="email" class="form-control form-control-lg glass-input"
                            placeholder="Email" required />

                        <input v-model="resetForm.otp" type="text" class="form-control form-control-lg glass-input"
                            placeholder="OTP Code" required />

                        <input v-model="resetForm.password" type="password"
                            class="form-control form-control-lg glass-input" placeholder="New Password" required />

                        <input v-model="resetForm.password_confirmation" type="password"
                            class="form-control form-control-lg glass-input" placeholder="Confirm Password" required />

                        <button class="btn btn-glow btn-lg rounded-3 py-3 fw-bold fs-5">
                            تغيير كلمة المرور
                        </button>
                    </form>

                    <!-- Switch link -->
                    <div class="text-center mt-4 text-white fs-6">
                        <span v-if="tab === 'register'">
                            Already have an account?
                            <a href="#" class="text-glow fw-medium" @click.prevent="tab = 'login'">Login</a>
                        </span>
                        <span v-else>
                            Don't have an account?
                            <a href="#" class="text-glow fw-medium" @click.prevent="tab = 'register'">Register</a>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, watch } from "vue";
import axios from "axios";
import { useRouter, useRoute } from "vue-router";

const tab = ref("login");
const forgotEmail = ref("");

const resetForm = ref({
    email: "",
    otp: "",
    password: "",
    password_confirmation: "",
});

const showPassword = ref(false);
const router = useRouter();

const loginForm = ref({ email: "", password: "" });
const registerForm = ref({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    image: null,
    position: "",
    date_of_birth: "",
    country: "",
});

const loading = ref(false);
const error = ref("");
const successMessage = ref("");
const previewImage = ref(null);

const logState = () => {
    console.clear();
    console.group("Current Auth State");
    console.groupEnd();
};

const countries = ref([
    { name: "أفغانستان", en: "Afghanistan", code: "AF" },
    { name: "ألبانيا", en: "Albania", code: "AL" },
    { name: "الجزائر", en: "Algeria", code: "DZ" },
    { name: "أندورا", en: "Andorra", code: "AD" },
    { name: "أنغولا", en: "Angola", code: "AO" },
    { name: "أنتيغوا وبربودا", en: "Antigua and Barbuda", code: "AG" },
    { name: "الأرجنتين", en: "Argentina", code: "AR" },
    { name: "أرمينيا", en: "Armenia", code: "AM" },
    { name: "أستراليا", en: "Australia", code: "AU" },
    { name: "النمسا", en: "Austria", code: "AT" },
    { name: "أذربيجان", en: "Azerbaijan", code: "AZ" },
    { name: "جزر البهاما", en: "Bahamas", code: "BS" },
    { name: "البحرين", en: "Bahrain", code: "BH" },
    { name: "بنغلاديش", en: "Bangladesh", code: "BD" },
    { name: "بربادوس", en: "Barbados", code: "BB" },
    { name: "بيلاروس", en: "Belarus", code: "BY" },
    { name: "بلجيكا", en: "Belgium", code: "BE" },
    { name: "بليز", en: "Belize", code: "BZ" },
    { name: "بنين", en: "Benin", code: "BJ" },
    { name: "بوتان", en: "Bhutan", code: "BT" },
    { name: "بوليفيا", en: "Bolivia", code: "BO" },
    { name: "البوسنة والهرسك", en: "Bosnia and Herzegovina", code: "BA" },
    { name: "بوتسوانا", en: "Botswana", code: "BW" },
    { name: "البرازيل", en: "Brazil", code: "BR" },
    { name: "بروناي", en: "Brunei", code: "BN" },
    { name: "بلغاريا", en: "Bulgaria", code: "BG" },
    { name: "بوركينا فاسو", en: "Burkina Faso", code: "BF" },
    { name: "بوروندي", en: "Burundi", code: "BI" },
    { name: "كمبوديا", en: "Cambodia", code: "KH" },
    { name: "الكاميرون", en: "Cameroon", code: "CM" },
    { name: "كندا", en: "Canada", code: "CA" },
    { name: "الرأس الأخضر", en: "Cape Verde", code: "CV" },
    { name: "جمهورية أفريقيا الوسطى", en: "Central African Republic", code: "CF" },
    { name: "تشاد", en: "Chad", code: "TD" },
    { name: "تشيلي", en: "Chile", code: "CL" },
    { name: "الصين", en: "China", code: "CN" },
    { name: "كولومبيا", en: "Colombia", code: "CO" },
    { name: "جزر القمر", en: "Comoros", code: "KM" },
    { name: "الكونغو", en: "Congo", code: "CG" },
    { name: "كوستاريكا", en: "Costa Rica", code: "CR" },
    { name: "كرواتيا", en: "Croatia", code: "HR" },
    { name: "كوبا", en: "Cuba", code: "CU" },
    { name: "قبرص", en: "Cyprus", code: "CY" },
    { name: "التشيك", en: "Czech Republic", code: "CZ" },
    { name: "الدنمارك", en: "Denmark", code: "DK" },
    { name: "جيبوتي", en: "Djibouti", code: "DJ" },
    { name: "دومينيكا", en: "Dominica", code: "DM" },
    { name: "جمهورية الدومينيكان", en: "Dominican Republic", code: "DO" },
    { name: "تيمور الشرقية", en: "East Timor", code: "TL" },
    { name: "الإكوادور", en: "Ecuador", code: "EC" },
    { name: "مصر", en: "Egypt", code: "EG" },
    { name: "السلفادور", en: "El Salvador", code: "SV" },
    { name: "غينيا الاستوائية", en: "Equatorial Guinea", code: "GQ" },
    { name: "إريتريا", en: "Eritrea", code: "ER" },
    { name: "إستونيا", en: "Estonia", code: "EE" },
    { name: "إثيوبيا", en: "Ethiopia", code: "ET" },
    { name: "فيجي", en: "Fiji", code: "FJ" },
    { name: "فنلندا", en: "Finland", code: "FI" },
    { name: "فرنسا", en: "France", code: "FR" },
    { name: "الغابون", en: "Gabon", code: "GA" },
    { name: "غامبيا", en: "Gambia", code: "GM" },
    { name: "جورجيا", en: "Georgia", code: "GE" },
    { name: "ألمانيا", en: "Germany", code: "DE" },
    { name: "غانا", en: "Ghana", code: "GH" },
    { name: "اليونان", en: "Greece", code: "GR" },
    { name: "غرينادا", en: "Grenada", code: "GD" },
    { name: "غواتيمالا", en: "Guatemala", code: "GT" },
    { name: "غينيا", en: "Guinea", code: "GN" },
    { name: "غينيا بيساو", en: "Guinea-Bissau", code: "GW" },
    { name: "غيانا", en: "Guyana", code: "GY" },
    { name: "هايتي", en: "Haiti", code: "HT" },
    { name: "هندوراس", en: "Honduras", code: "HN" },
    { name: "هنغاريا", en: "Hungary", code: "HU" },
    { name: "آيسلندا", en: "Iceland", code: "IS" },
    { name: "الهند", en: "India", code: "IN" },
    { name: "إندونيسيا", en: "Indonesia", code: "ID" },
    { name: "إيران", en: "Iran", code: "IR" },
    { name: "العراق", en: "Iraq", code: "IQ" },
    { name: "أيرلندا", en: "Ireland", code: "IE" },
    { name: "إسرائيل", en: "Israel", code: "IL" },
    { name: "إيطاليا", en: "Italy", code: "IT" },
    { name: "جامايكا", en: "Jamaica", code: "JM" },
    { name: "اليابان", en: "Japan", code: "JP" },
    { name: "الأردن", en: "Jordan", code: "JO" },
    { name: "كازاخستان", en: "Kazakhstan", code: "KZ" },
    { name: "كينيا", en: "Kenya", code: "KE" },
    { name: "كيريباتي", en: "Kiribati", code: "KI" },
    { name: "كوريا الشمالية", en: "Korea, North", code: "KP" },
    { name: "كوريا الجنوبية", en: "Korea, South", code: "KR" },
    { name: "الكويت", en: "Kuwait", code: "KW" },
    { name: "قرغيزستان", en: "Kyrgyzstan", code: "KG" },
    { name: "لاوس", en: "Laos", code: "LA" },
    { name: "لاتفيا", en: "Latvia", code: "LV" },
    { name: "لبنان", en: "Lebanon", code: "LB" },
    { name: "ليسوتو", en: "Lesotho", code: "LS" },
    { name: "ليبيريا", en: "Liberia", code: "LR" },
    { name: "ليبيا", en: "Libya", code: "LY" },
    { name: "ليختنشتاين", en: "Liechtenstein", code: "LI" },
    { name: "ليتوانيا", en: "Lithuania", code: "LT" },
    { name: "لوكسمبورغ", en: "Luxembourg", code: "LU" },
    { name: "مدغشقر", en: "Madagascar", code: "MG" },
    { name: "مالاوي", en: "Malawi", code: "MW" },
    { name: "ماليزيا", en: "Malaysia", code: "MY" },
    { name: "جزر المالديف", en: "Maldives", code: "MV" },
    { name: "مالي", en: "Mali", code: "ML" },
    { name: "مالطا", en: "Malta", code: "MT" },
    { name: "جزر مارشال", en: "Marshall Islands", code: "MH" },
    { name: "موريتانيا", en: "Mauritania", code: "MR" },
    { name: "موريشيوس", en: "Mauritius", code: "MU" },
    { name: "المكسيك", en: "Mexico", code: "MX" },
    { name: "ميكرونيزيا", en: "Micronesia", code: "FM" },
    { name: "مولدوفا", en: "Moldova", code: "MD" },
    { name: "موناكو", en: "Monaco", code: "MC" },
    { name: "منغوليا", en: "Mongolia", code: "MN" },
    { name: "الجبل الأسود", en: "Montenegro", code: "ME" },
    { name: "المغرب", en: "Morocco", code: "MA" },
    { name: "موزمبيق", en: "Mozambique", code: "MZ" },
    { name: "ميانمار", en: "Myanmar", code: "MM" },
    { name: "ناميبيا", en: "Namibia", code: "NA" },
    { name: "ناورو", en: "Nauru", code: "NR" },
    { name: "نيبال", en: "Nepal", code: "NP" },
    { name: "هولندا", en: "Netherlands", code: "NL" },
    { name: "نيوزيلندا", en: "New Zealand", code: "NZ" },
    { name: "نيكاراغوا", en: "Nicaragua", code: "NI" },
    { name: "النيجر", en: "Niger", code: "NE" },
    { name: "نيجيريا", en: "Nigeria", code: "NG" },
    { name: "مقدونيا الشمالية", en: "North Macedonia", code: "MK" },
    { name: "النرويج", en: "Norway", code: "NO" },
    { name: "عمان", en: "Oman", code: "OM" },
    { name: "باكستان", en: "Pakistan", code: "PK" },
    { name: "بالاو", en: "Palau", code: "PW" },
    { name: "فلسطين", en: "Palestine", code: "PS" },
    { name: "بنما", en: "Panama", code: "PA" },
    { name: "بابوا غينيا الجديدة", en: "Papua New Guinea", code: "PG" },
    { name: "باراغواي", en: "Paraguay", code: "PY" },
    { name: "بيرو", en: "Peru", code: "PE" },
    { name: "الفلبين", en: "Philippines", code: "PH" },
    { name: "بولندا", en: "Poland", code: "PL" },
    { name: "البرتغال", en: "Portugal", code: "PT" },
    { name: "قطر", en: "Qatar", code: "QA" },
    { name: "رومانيا", en: "Romania", code: "RO" },
    { name: "روسيا", en: "Russia", code: "RU" },
    { name: "رواندا", en: "Rwanda", code: "RW" },
    { name: "سانت كيتس ونيفيس", en: "Saint Kitts and Nevis", code: "KN" },
    { name: "سانت لوسيا", en: "Saint Lucia", code: "LC" },
    { name: "سانت فينسنت والغرينادين", en: "Saint Vincent and the Grenadines", code: "VC" },
    { name: "ساموا", en: "Samoa", code: "WS" },
    { name: "سان مارينو", en: "San Marino", code: "SM" },
    { name: "ساو تومي وبرينسيب", en: "Sao Tome and Principe", code: "ST" },
    { name: "السعودية", en: "Saudi Arabia", code: "SA" },
    { name: "السنغال", en: "Senegal", code: "SN" },
    { name: "صربيا", en: "Serbia", code: "RS" },
    { name: "سيشيل", en: "Seychelles", code: "SC" },
    { name: "سيرا ليون", en: "Sierra Leone", code: "SL" },
    { name: "سنغافورة", en: "Singapore", code: "SG" },
    { name: "سلوفاكيا", en: "Slovakia", code: "SK" },
    { name: "سلوفينيا", en: "Slovenia", code: "SI" },
    { name: "جزر سليمان", en: "Solomon Islands", code: "SB" },
    { name: "الصومال", en: "Somalia", code: "SO" },
    { name: "جنوب أفريقيا", en: "South Africa", code: "ZA" },
    { name: "إسبانيا", en: "Spain", code: "ES" },
    { name: "سريلانكا", en: "Sri Lanka", code: "LK" },
    { name: "السودان", en: "Sudan", code: "SD" },
    { name: "سورينام", en: "Suriname", code: "SR" },
    { name: "السويد", en: "Sweden", code: "SE" },
    { name: "سويسرا", en: "Switzerland", code: "CH" },
    { name: "سوريا", en: "Syria", code: "SY" },
    { name: "تايوان", en: "Taiwan", code: "TW" },
    { name: "طاجيكستان", en: "Tajikistan", code: "TJ" },
    { name: "تنزانيا", en: "Tanzania", code: "TZ" },
    { name: "تايلاند", en: "Thailand", code: "TH" },
    { name: "توغو", en: "Togo", code: "TG" },
    { name: "تونغا", en: "Tonga", code: "TO" },
    { name: "ترينيداد وتوباغو", en: "Trinidad and Tobago", code: "TT" },
    { name: "تونس", en: "Tunisia", code: "TN" },
    { name: "تركيا", en: "Türkiye", code: "TR" },
    { name: "تركمانستان", en: "Turkmenistan", code: "TM" },
    { name: "توفالو", en: "Tuvalu", code: "TV" },
    { name: "أوغندا", en: "Uganda", code: "UG" },
    { name: "أوكرانيا", en: "Ukraine", code: "UA" },
    { name: "الإمارات العربية المتحدة", en: "United Arab Emirates", code: "AE" },
    { name: "المملكة المتحدة", en: "United Kingdom", code: "GB" },
    { name: "الولايات المتحدة", en: "United States", code: "US" },
    { name: "أوروغواي", en: "Uruguay", code: "UY" },
    { name: "أوزبكستان", en: "Uzbekistan", code: "UZ" },
    { name: "فانواتو", en: "Vanuatu", code: "VU" },
    { name: "الفاتيكان", en: "Vatican City", code: "VA" },
    { name: "فنزويلا", en: "Venezuela", code: "VE" },
    { name: "فيتنام", en: "Vietnam", code: "VN" },
    { name: "اليمن", en: "Yemen", code: "YE" },
    { name: "زامبيا", en: "Zambia", code: "ZM" },
    { name: "زيمبابوي", en: "Zimbabwe", code: "ZW" },
]);

watch(tab, () => logState());
watch(showPassword, () => logState());
watch(loading, () => logState());
watch(error, () => logState());
watch(successMessage, () => logState());
watch(loginForm, () => logState(), { deep: true });
watch(registerForm, () => logState(), { deep: true });
watch(resetForm, () => logState(), { deep: true });
watch(forgotEmail, () => logState());

const handleAppleLogin = async () => {
    try {
        const res = await axios.get("/v1/users/apple-login");
        const appleUrl = res.data.url;

        window.location.href = appleUrl;
    } catch (err) {
        console.error(err);
        error.value = "فشل الاتصال بـ Apple";
    }
};
const saveTokenAndRedirect = (token, role = "user") => {
    console.log("saveTokenAndRedirect called", { token, role });
    if (!token) return;

    localStorage.setItem("auth_token", token);
    localStorage.setItem("user_role", role);

    axios.defaults.headers.common["Authorization"] = `Bearer ${token}`;

    const url = new URL(window.location.href);
    url.searchParams.delete("token");
    window.history.replaceState({}, document.title, url);

    if (role === "admin") {
        router.push("/admin");
    } else {
        router.push("/");
    }
};

onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get("token");
    const role = urlParams.get("role");
    const errorMsg = urlParams.get("error");

    if (errorMsg) {
        error.value = decodeURIComponent(errorMsg);
        return;
    }

    if (token) {
        localStorage.setItem("auth_token", token);
        localStorage.setItem("user_role", role || "user");
        axios.defaults.headers.common["Authorization"] = `Bearer ${token}`;
        const url = new URL(window.location.href);
        url.search = "";
        window.history.replaceState({}, document.title, url);
        router.push("/");
    }
});

const handleLogin = async () => {
    console.log("handleLogin called with:", loginForm.value);
    loading.value = true;
    error.value = "";

    try {
        const res = await axios.post("/v1/users/login", loginForm.value);
        console.log("Login response:", res.data);

        if (res.data.status === "success") {
            saveTokenAndRedirect(res.data.data.token, res.data.data.user.role);
        } else {
            error.value = res.data.message || "Login failed";
        }
    } catch (err) {
        console.error("Login error:", err);
        error.value = err.response?.data?.message || "Error during login";
    } finally {
        loading.value = false;
    }
};

const handleRegister = async () => {
    console.log("handleRegister called with:", registerForm.value);
    loading.value = true;
    error.value = "";

    try {
        const formData = new FormData();
        Object.keys(registerForm.value).forEach((key) => {
            formData.append(key, registerForm.value[key]);
        });
        const res = await axios.post("/v1/users/register", formData, {
            headers: { "Content-Type": "multipart/form-data" },
        });
        console.log("Register response:", res.data);

        if (res.data.status === "success") {
            successMessage.value = "Account created successfully";
            saveTokenAndRedirect(res.data.data.token);
        } else {
            error.value = res.data.message || "Registration failed";
        }
    } catch (err) {
        console.error("Register error:", err);
        error.value = err.response?.data?.message || "Error during registration";
    } finally {
        loading.value = false;
    }
};

const handleForgot = async () => {
    console.log("handleForgot called with:", forgotEmail.value);
    error.value = "";
    try {
        const res = await axios.post("/v1/users/forgot-password", {
            email: forgotEmail.value,
        });
        console.log("Forgot password response:", res.data);

        resetForm.value.email = forgotEmail.value;
        successMessage.value = "تم إرسال الكود على الإيميل";
        tab.value = "reset";
    } catch (err) {
        console.error("Forgot password error:", err);
        error.value = err.response?.data?.message || "حصل خطأ";
    }
};

const handleReset = async () => {
    console.log("handleReset called with:", resetForm.value);
    error.value = "";
    try {
        const res = await axios.post("/v1/users/reset-password", resetForm.value);
        console.log("Reset password response:", res.data);

        successMessage.value = "تم تغيير كلمة المرور بنجاح";
        tab.value = "login";
    } catch (err) {
        console.error("Reset password error:", err);
        error.value = err.response?.data?.message || "الكود غير صحيح";
    }
};

const handleGoogleLogin = async () => {
    try {
        const res = await axios.get("/v1/users/google-login");
        const googleUrl = res.data.url;

        window.location.href = googleUrl;
    } catch (err) {
        console.error(err);
        error.value = "فشل الاتصال بجوجل";
    }
};

const handleFacebookLogin = async () => {
    try {
        const res = await axios.get("/v1/users/facebook-login");
        const facebookUrl = res.data.url;
        window.location.href = facebookUrl;
    } catch (err) {
        console.error(err);
        error.value = "فشل الاتصال بفيسبوك";
    }
};
const handleImageUpload = (e) => {
    const file = e.target.files[0];
    if (!file) return;

    registerForm.value.image = file;
    previewImage.value = URL.createObjectURL(file);
};
</script>

<style scoped>
.btn-apple {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: white;
    transition: all 0.4s ease;
    overflow: hidden;
    position: relative;
}

.btn-apple:hover {
    transform: scale(1.04);
    color: white;
}

/* Underline color appears on hover */
.btn-apple::before {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, #5c4033, #8b5e3c);
    opacity: 0;
    transition: opacity 0.4s ease;
    z-index: -1;
}

.btn-apple:hover::before {
    opacity: 1;
}

/* ─── Common ─── */
.waves {
    background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23D4AF37" fill-opacity="0.08" d="M0,96L48,112C96,128,192,160,288,176C384,192,480,192,576,186.7C672,181,768,171,864,154.7C960,139,1056,117,1152,122.7C1248,128,1344,160,1392,176L1440,192L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') bottom no-repeat;
    background-size: cover;
    animation: wave 18s linear infinite alternate;
    opacity: 0.6;
}

.glass-card {
    background: rgba(30, 41, 59, 0.35);
    border: 1px solid rgba(212, 175, 55, 0.18);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.45), inset 0 0 20px rgba(212, 175, 55, 0.08);
    transition: all 0.4s ease;
}

.glass-input {
    background: rgba(30, 41, 59, 0.45);
    border: 1px solid rgba(212, 175, 55, 0.25);
    color: white;
    transition: all 0.3s;
}

.glass-input:focus {
    background: rgba(51, 65, 85, 0.55);
    border-color: #d4af37;
    box-shadow: 0 0 0 0.25rem rgba(212, 175, 55, 0.25);
    outline: none;
}

.glass-input::placeholder {
    color: rgba(255, 255, 255, 0.55);
}

.btn-glow {
    background: linear-gradient(90deg, #d4af37, #eab308, #fbbf24);
    border: none;
    color: #0f172a;
    font-weight: 600;
    box-shadow: 0 6px 20px rgba(212, 175, 55, 0.4);
    transition: all 0.35s;
}

.btn-glow:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 32px rgba(212, 175, 55, 0.55);
    filter: brightness(1.08);
}

/* ─── Tabs ─── */
.nav-link {
    position: relative;
    color: rgba(255, 255, 255, 0.75);
    transition: color 0.3s ease;
}

.nav-link:hover {
    color: white;
}

.nav-link::after {
    content: "";
    position: absolute;
    bottom: -4px;
    left: 50%;
    width: 0;
    height: 3px;
    background: linear-gradient(90deg, #d4af37, #eab308);
    border-radius: 3px;
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    transform: translateX(-50%);
}

.nav-link:hover::after,
.active-tab::after {
    width: 70%;
}

/* Active tab always has underline */
.active-tab {
    color: white !important;
}

/* ─── Google Button ─── */
.btn-google {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: white;
    transition: all 0.4s ease;
    overflow: hidden;
    position: relative;
}

.btn-google:hover {
    transform: scale(1.04);
    color: white;
}

/* Underline color appears on hover */
.btn-google::before {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, #d4af37, #eab308);
    opacity: 0;
    transition: opacity 0.4s ease;
    z-index: -1;
}

.btn-google:hover::before {
    opacity: 1;
}

/* ─── Dark Theme (default) ─── */
[data-theme="dark"] .glass-card {
    background: rgba(30, 41, 59, 0.35);
    border-color: rgba(212, 175, 55, 0.18);
}

[data-theme="dark"] .glass-input {
    background: rgba(30, 41, 59, 0.45);
    border-color: rgba(212, 175, 55, 0.25);
    color: white;
}

[data-theme="dark"] .glass-input::placeholder {
    color: rgba(255, 255, 255, 0.55);
}

[data-theme="dark"] .btn-glow {
    background: linear-gradient(90deg, #d4af37, #eab308, #fbbf24);
    color: #0f172a;
}

/* ─── Light Theme ─── */
[data-theme="light"] .auth-container,
[data-theme="light"] .auth-container * {
    color: #111827;
}

[data-theme="light"] .glass-card {
    background: rgba(255, 255, 255, 0.75);
    border: 1px solid rgba(0, 0, 0, 0.08);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
    backdrop-filter: blur(12px);
}

[data-theme="light"] .glass-input {
    background: rgba(243, 244, 246, 0.9);
    border: 1px solid #d1d5db;
    color: #111827;
}

[data-theme="light"] .glass-input:focus {
    border-color: #111827;
    box-shadow: 0 0 0 0.25rem rgba(17, 24, 39, 0.15);
}

[data-theme="light"] .glass-input::placeholder {
    color: #6b7280;
}

[data-theme="light"] .btn-glow {
    background: #111827;
    color: white;
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
}

[data-theme="light"] .btn-glow:hover {
    background: #1f2937;
    transform: translateY(-2px);
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.2);
}

/* Light theme → tabs: black underline */
[data-theme="light"] .nav-link::after,
[data-theme="light"] .active-tab::after {
    background: #111827 !important;
}

/* Light theme → Google button hover becomes black */
[data-theme="light"] .btn-google {
    background: rgba(0, 0, 0, 0.04);
    border: 1px solid rgba(0, 0, 0, 0.12);
    color: #111827;
}

[data-theme="light"] .btn-google:hover {
    color: white;
}

[data-theme="light"] .btn-google::before {
    background: #111827;
}

[data-theme="light"] .btn-apple {
    background: rgba(0, 0, 0, 0.04);
    border: 1px solid rgba(0, 0, 0, 0.12);
    color: #111827;
}

[data-theme="light"] .btn-apple:hover {
    color: white;
}

[data-theme="light"] .btn-apple::before {
    background: #111827;
}

/* Disable gold glow effects in light mode */
[data-theme="light"] .text-glow,
[data-theme="light"] .logo-glow,
[data-theme="light"] .active-tab {
    filter: none !important;
    background: none !important;
    -webkit-background-clip: unset !important;
    -webkit-text-fill-color: #111827 !important;
}

[data-theme="light"] .logo-glow {
    filter: none;
}

/* Waves in light mode → very subtle or hidden */
[data-theme="light"] .waves {
    opacity: 0.03;
    filter: brightness(0.4);
}

.btn-facebook {
    background: rgba(59, 89, 152, 0.08);
    border: 1px solid rgba(59, 89, 152, 0.15);
    color: white;
    transition: all 0.4s ease;
    overflow: hidden;
    position: relative;
}

.btn-facebook:hover {
    transform: scale(1.04);
    color: white;
}

.btn-facebook::before {
    content: "";
    position: absolute;
    inset: 0;
    background: #3b5998;
    opacity: 0;
    transition: opacity 0.4s ease;
    z-index: -1;
}

.btn-facebook:hover::before {
    opacity: 1;
}

.upload-avatar {
    cursor: pointer;
    display: inline-block;
}

.avatar-preview,
.avatar-placeholder {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(212, 175, 55, 0.5);
}

.avatar-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(212, 175, 55, 0.15);
    color: white;
    font-size: 13px;
    font-weight: 600;
}
</style>
