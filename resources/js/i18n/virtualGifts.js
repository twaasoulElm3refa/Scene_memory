import weekly from './weeklyCompetition';

const groups = {
    core: 'sendSupport|choose|recipient|quantity|total|split|walletBalance|paymentMethod|wallet|paypal|confirm|sentSuccess|paymentFailed|insufficient|loadError|empty|close|unavailable|verify|history|sent|received|paymentStatus|earnings|holding|requestWithdrawal|amount|currency|destination|withdrawals|cancel|withdrawalFailed|giftCount'.split('|'),
    states: 'pending|paid|failed|cancelled|partially_refunded|refunded|reversed|available|reserved|settled|recovery|approved|rejected'.split('|'),
    admin: 'adminTitle|adminError|catalog|transactions|report|name|catalogCode|description|price|order|active|inactive|image|language|translatedName|translatedDescription|save|create|edit|deactivate|all|filter|review|commission|processingFees|notApplicable|unknown|audit|confirmRefund|clearReview|proof|ledgerMovements|provisional|gross|recipientEarnings|platformNet|walletVolume|paypalVolume|refunds|popularGifts|supportedEvents|recipients|approve|reject|recordPayout'.split('|'),
    samples: 'coffee|cake|rose|gift-box|trophy'.split('|'),
};

const rows = {
    en: {
        core: 'Send Support|Choose a gift|Recipient|Quantity|Total|Creator earns 85%; Scemory retains 15%.|Wallet balance|Payment method|Pay from wallet|Pay with PayPal|Confirm support|Gift sent|Payment could not be verified. Retry with the same selection.|Insufficient balance or wallet currency mismatch.|Could not load gifts. Please try again.|No gifts yet.|Close|Unavailable|Verify payment|Gifts & earnings|Sent gifts|Received gifts|Payment status|Creator earnings|Earnings are held for {days} days before release.|Request withdrawal|Amount|Currency|Payout destination|Withdrawals|Cancel|Withdrawal could not be completed. Check available earnings or contact support.|{count} gifts',
        states: 'Pending|Paid|Failed|Cancelled|Partially refunded|Refunded|Reversed|Available|Reserved for payout|Settled|Recovery due|Approved|Rejected',
        admin: 'Virtual Gifts|Could not save or verify this operation. Check the details and retry.|Catalog|Transactions|Financial report|Name|Catalog code|Description|Price|Display order|Active|Inactive|Image|Language|Translated name|Translated description|Save|Create Gift|Edit|Deactivate|All|Filter|Requires review|Platform commission|Processing fees|Not applicable|Unknown|Audit history|Confirm refund of the remaining gift value:|Clear review|External proof / reference|{count} ledger movements|Provisional: fees unknown|Gross payments|Recipient earnings|Net platform revenue|Wallet gift volume|PayPal gift volume|Refunds|Popular gifts|Supported events|Recipients|Approve|Reject|Record completed payout',
        samples: 'Coffee Cup|Cake Slice|Rose|Gift Box|Golden Trophy',
    },
    ar: {
        core: 'إرسال دعم|اختر هدية|المستلم|عدد الهدايا|إجمالي المبلغ|يحصل المبدع على 85% وتحتفظ سكيموري بنسبة 15%.|رصيد المحفظة|طريقة الدفع|ادفع من المحفظة|الدفع عبر PayPal|تأكيد إرسال الدعم|تم إرسال الهدية|تعذر التحقق من الدفع. أعد المحاولة بالاختيار نفسه.|الرصيد غير كافٍ أو عملة المحفظة غير متوافقة.|تعذر تحميل الهدايا. حاول مجددًا.|لا توجد هدايا بعد.|إغلاق|غير متاح|التحقق من الدفع|الهدايا والأرباح|الهدايا المرسلة|الهدايا المستلمة|حالة الدفع|أرباح المبدع|تُعلّق الأرباح لمدة {days} أيام قبل إتاحتها.|طلب سحب|المبلغ|العملة|وجهة استلام الدفعة|طلبات السحب|إلغاء|تعذر إتمام السحب. تحقق من الأرباح المتاحة أو تواصل مع الدعم.|{count} هدية',
        states: 'معلق|مدفوع|فشل|ملغى|مسترد جزئيًا|مسترد|معكوس|متاح|محجوز للسحب|تم صرفه|مبلغ مطلوب استرداده|موافق عليه|مرفوض',
        admin: 'الهدايا الافتراضية|تعذر حفظ العملية أو التحقق منها. راجع البيانات وحاول مجددًا.|كتالوج الهدايا|سجل المعاملات|التقرير المالي|الاسم|رمز الهدية|الوصف|قيمة الهدية|ترتيب العرض|مفعّل|غير مفعّل|الصورة|اللغة|الاسم المترجم|الوصف المترجم|حفظ|إنشاء هدية|تعديل|تعطيل|الكل|تصفية|يتطلب مراجعة|عمولة المنصة|رسوم معالجة الدفع|لا ينطبق|غير معروف|سجل التدقيق|تأكيد استرداد القيمة المتبقية للهدية:|إنهاء المراجعة|إثبات أو مرجع خارجي|{count} حركة في دفتر الحسابات|مؤقت: الرسوم غير معروفة|إجمالي المدفوعات|أرباح المستلمين|صافي إيرادات المنصة|قيمة هدايا المحفظة|قيمة هدايا PayPal|المبالغ المستردة|الهدايا الأكثر رواجًا|الأحداث المدعومة|المستلمون|موافقة|رفض|تسجيل دفعة مكتملة',
        samples: 'فنجان قهوة|قطعة كعك|وردة|صندوق هدايا|كأس ذهبي',
    },
    fr: {
        core: 'Envoyer un soutien|Choisir un cadeau|Destinataire|Quantité|Total|Le créateur reçoit 85 % ; Scemory conserve 15 %.|Solde du portefeuille|Mode de paiement|Payer avec le portefeuille|Payer avec PayPal|Confirmer le soutien|Cadeau envoyé|Paiement non vérifié. Réessayez avec la même sélection.|Solde insuffisant ou devise incompatible.|Impossible de charger les cadeaux. Réessayez.|Aucun cadeau pour le moment.|Fermer|Indisponible|Vérifier le paiement|Cadeaux et revenus|Cadeaux envoyés|Cadeaux reçus|État du paiement|Revenus du créateur|Les revenus sont retenus pendant {days} jours.|Demander un retrait|Montant|Devise|Destination du versement|Retraits|Annuler|Retrait impossible. Vérifiez les revenus disponibles ou contactez l’assistance.|{count} cadeaux',
        states: 'En attente|Payé|Échec|Annulé|Partiellement remboursé|Remboursé|Annulé financièrement|Disponible|Réservé au versement|Versé|À recouvrer|Approuvé|Refusé',
        admin: 'Cadeaux virtuels|Impossible d’enregistrer ou de vérifier. Vérifiez les données et réessayez.|Catalogue|Transactions|Rapport financier|Nom|Code du cadeau|Description|Prix|Ordre d’affichage|Actif|Inactif|Image|Langue|Nom traduit|Description traduite|Enregistrer|Créer un cadeau|Modifier|Désactiver|Tous|Filtrer|À examiner|Commission de la plateforme|Frais de traitement|Sans objet|Inconnu|Historique d’audit|Confirmer le remboursement du solde du cadeau :|Clore l’examen|Justificatif / référence externe|{count} mouvements comptables|Provisoire : frais inconnus|Paiements bruts|Revenus des destinataires|Revenu net de la plateforme|Volume des cadeaux par portefeuille|Volume des cadeaux PayPal|Remboursements|Cadeaux populaires|Événements soutenus|Destinataires|Approuver|Refuser|Enregistrer un versement effectué',
        samples: 'Tasse de café|Part de gâteau|Rose|Boîte cadeau|Trophée doré',
    },
    de: {
        core: 'Unterstützung senden|Geschenk auswählen|Empfänger|Anzahl|Gesamt|Der Ersteller erhält 85 %; Scemory behält 15 %.|Guthaben|Zahlungsart|Mit Guthaben bezahlen|Mit PayPal bezahlen|Unterstützung bestätigen|Geschenk gesendet|Zahlung nicht bestätigt. Mit derselben Auswahl erneut versuchen.|Unzureichendes Guthaben oder falsche Währung.|Geschenke konnten nicht geladen werden. Erneut versuchen.|Noch keine Geschenke.|Schließen|Nicht verfügbar|Zahlung prüfen|Geschenke und Einnahmen|Gesendete Geschenke|Erhaltene Geschenke|Zahlungsstatus|Einnahmen des Erstellers|Einnahmen werden {days} Tage zurückgehalten.|Auszahlung beantragen|Betrag|Währung|Auszahlungsziel|Auszahlungen|Abbrechen|Auszahlung nicht möglich. Verfügbare Einnahmen prüfen oder Support kontaktieren.|{count} Geschenke',
        states: 'Ausstehend|Bezahlt|Fehlgeschlagen|Storniert|Teilweise erstattet|Erstattet|Rückgebucht|Verfügbar|Zur Auszahlung reserviert|Ausgezahlt|Rückforderung offen|Genehmigt|Abgelehnt',
        admin: 'Virtuelle Geschenke|Speichern oder Prüfen fehlgeschlagen. Angaben prüfen und erneut versuchen.|Katalog|Transaktionen|Finanzbericht|Name|Geschenkcode|Beschreibung|Preis|Anzeigereihenfolge|Aktiv|Inaktiv|Bild|Sprache|Übersetzter Name|Übersetzte Beschreibung|Speichern|Geschenk erstellen|Bearbeiten|Deaktivieren|Alle|Filtern|Prüfung erforderlich|Plattformprovision|Bearbeitungsgebühren|Nicht zutreffend|Unbekannt|Prüfprotokoll|Erstattung des verbleibenden Geschenkwerts bestätigen:|Prüfung abschließen|Externer Beleg / Referenz|{count} Kontobewegungen|Vorläufig: Gebühren unbekannt|Bruttozahlungen|Empfängereinnahmen|Nettoerlös der Plattform|Geschenkvolumen aus Guthaben|PayPal-Geschenkvolumen|Erstattungen|Beliebte Geschenke|Unterstützte Ereignisse|Empfänger|Genehmigen|Ablehnen|Erfolgte Auszahlung erfassen',
        samples: 'Kaffeetasse|Kuchenstück|Rose|Geschenkbox|Goldener Pokal',
    },
    es: {
        core: 'Enviar apoyo|Elige un regalo|Destinatario|Cantidad|Total|El creador recibe el 85 %; Scemory conserva el 15 %.|Saldo del monedero|Método de pago|Pagar con monedero|Pagar con PayPal|Confirmar apoyo|Regalo enviado|No se verificó el pago. Reintenta con la misma selección.|Saldo insuficiente o moneda incompatible.|No se pudieron cargar los regalos. Reintenta.|Aún no hay regalos.|Cerrar|No disponible|Verificar pago|Regalos e ingresos|Regalos enviados|Regalos recibidos|Estado del pago|Ingresos del creador|Los ingresos se retienen durante {days} días.|Solicitar retiro|Importe|Moneda|Destino del pago|Retiros|Cancelar|No se pudo retirar. Revisa los ingresos disponibles o contacta con soporte.|{count} regalos',
        states: 'Pendiente|Pagado|Fallido|Cancelado|Reembolsado parcialmente|Reembolsado|Revertido|Disponible|Reservado para pago|Liquidado|Recuperación pendiente|Aprobado|Rechazado',
        admin: 'Regalos virtuales|No se pudo guardar o verificar. Revisa los datos y reintenta.|Catálogo|Transacciones|Informe financiero|Nombre|Código del regalo|Descripción|Precio|Orden de visualización|Activo|Inactivo|Imagen|Idioma|Nombre traducido|Descripción traducida|Guardar|Crear regalo|Editar|Desactivar|Todos|Filtrar|Requiere revisión|Comisión de la plataforma|Comisiones de procesamiento|No corresponde|Desconocido|Historial de auditoría|Confirmar reembolso del valor restante del regalo:|Finalizar revisión|Justificante / referencia externa|{count} movimientos contables|Provisional: comisiones desconocidas|Pagos brutos|Ingresos de destinatarios|Ingresos netos de la plataforma|Volumen de regalos con monedero|Volumen de regalos PayPal|Reembolsos|Regalos populares|Eventos apoyados|Destinatarios|Aprobar|Rechazar|Registrar pago completado',
        samples: 'Taza de café|Porción de pastel|Rosa|Caja de regalo|Trofeo dorado',
    },
    it: {
        core: 'Invia supporto|Scegli un regalo|Destinatario|Quantità|Totale|Il creatore riceve l’85%; Scemory trattiene il 15%.|Saldo del portafoglio|Metodo di pagamento|Paga dal portafoglio|Paga con PayPal|Conferma supporto|Regalo inviato|Pagamento non verificato. Riprova con la stessa selezione.|Saldo insufficiente o valuta incompatibile.|Impossibile caricare i regali. Riprova.|Nessun regalo per ora.|Chiudi|Non disponibile|Verifica pagamento|Regali e guadagni|Regali inviati|Regali ricevuti|Stato del pagamento|Guadagni del creatore|I guadagni sono trattenuti per {days} giorni.|Richiedi prelievo|Importo|Valuta|Destinazione del pagamento|Prelievi|Annulla|Prelievo non riuscito. Controlla i guadagni disponibili o contatta l’assistenza.|{count} regali',
        states: 'In attesa|Pagato|Fallito|Annullato|Rimborsato parzialmente|Rimborsato|Stornato|Disponibile|Riservato al pagamento|Liquidato|Da recuperare|Approvato|Rifiutato',
        admin: 'Regali virtuali|Salvataggio o verifica non riusciti. Controlla i dati e riprova.|Catalogo|Transazioni|Rapporto finanziario|Nome|Codice regalo|Descrizione|Prezzo|Ordine di visualizzazione|Attivo|Inattivo|Immagine|Lingua|Nome tradotto|Descrizione tradotta|Salva|Crea regalo|Modifica|Disattiva|Tutti|Filtra|Revisione necessaria|Commissione della piattaforma|Costi di elaborazione|Non applicabile|Sconosciuto|Registro di controllo|Conferma il rimborso del valore residuo del regalo:|Chiudi revisione|Prova / riferimento esterno|{count} movimenti contabili|Provvisorio: costi sconosciuti|Pagamenti lordi|Guadagni dei destinatari|Ricavi netti della piattaforma|Volume regali dal portafoglio|Volume regali PayPal|Rimborsi|Regali popolari|Eventi sostenuti|Destinatari|Approva|Rifiuta|Registra pagamento completato',
        samples: 'Tazza di caffè|Fetta di torta|Rosa|Scatola regalo|Trofeo dorato',
    },
    ru: {
        core: 'Поддержать|Выберите подарок|Получатель|Количество|Итого|Автор получает 85%; Scemory удерживает 15%.|Баланс кошелька|Способ оплаты|Оплатить из кошелька|Оплатить через PayPal|Подтвердить поддержку|Подарок отправлен|Платёж не подтверждён. Повторите с тем же выбором.|Недостаточно средств или несовместимая валюта.|Не удалось загрузить подарки. Повторите попытку.|Подарков пока нет.|Закрыть|Недоступно|Проверить платёж|Подарки и доходы|Отправленные подарки|Полученные подарки|Статус платежа|Доходы автора|Доходы удерживаются {days} дней.|Запросить вывод|Сумма|Валюта|Реквизиты выплаты|Вывод средств|Отмена|Вывод не выполнен. Проверьте доступные доходы или обратитесь в поддержку.|Подарков: {count}',
        states: 'В ожидании|Оплачено|Ошибка|Отменено|Частичный возврат|Возвращено|Сторнировано|Доступно|Зарезервировано для выплаты|Выплачено|К взысканию|Одобрено|Отклонено',
        admin: 'Виртуальные подарки|Не удалось сохранить или проверить. Проверьте данные и повторите.|Каталог|Транзакции|Финансовый отчёт|Название|Код подарка|Описание|Цена|Порядок отображения|Активен|Неактивен|Изображение|Язык|Перевод названия|Перевод описания|Сохранить|Создать подарок|Изменить|Отключить|Все|Фильтр|Требуется проверка|Комиссия платформы|Расходы на обработку|Не применимо|Неизвестно|Журнал аудита|Подтвердите возврат оставшейся стоимости подарка:|Завершить проверку|Внешний документ / номер|Проводок: {count}|Предварительно: расходы неизвестны|Валовые платежи|Доходы получателей|Чистый доход платформы|Объём подарков из кошелька|Объём подарков PayPal|Возвраты|Популярные подарки|Поддержанные события|Получатели|Одобрить|Отклонить|Записать выполненную выплату',
        samples: 'Чашка кофе|Кусочек торта|Роза|Подарочная коробка|Золотой кубок',
    },
    tr: {
        core: 'Destek gönder|Hediye seç|Alıcı|Adet|Toplam|İçerik üreticisi %85 alır; Scemory %15 tutar.|Cüzdan bakiyesi|Ödeme yöntemi|Cüzdandan öde|PayPal ile öde|Desteği onayla|Hediye gönderildi|Ödeme doğrulanamadı. Aynı seçimle tekrar deneyin.|Yetersiz bakiye veya uyumsuz para birimi.|Hediyeler yüklenemedi. Tekrar deneyin.|Henüz hediye yok.|Kapat|Kullanılamıyor|Ödemeyi doğrula|Hediyeler ve kazançlar|Gönderilen hediyeler|Alınan hediyeler|Ödeme durumu|Üretici kazançları|Kazançlar {days} gün bekletilir.|Para çekme talebi|Tutar|Para birimi|Ödeme hedefi|Para çekimleri|İptal|Para çekilemedi. Kullanılabilir kazancı kontrol edin veya destekle iletişime geçin.|{count} hediye',
        states: 'Bekliyor|Ödendi|Başarısız|İptal edildi|Kısmen iade edildi|İade edildi|Tersine çevrildi|Kullanılabilir|Ödeme için ayrıldı|Ödendi ve kapandı|Tahsil edilecek|Onaylandı|Reddedildi',
        admin: 'Sanal hediyeler|Kaydedilemedi veya doğrulanamadı. Bilgileri kontrol edip tekrar deneyin.|Katalog|İşlemler|Mali rapor|Ad|Hediye kodu|Açıklama|Fiyat|Görüntüleme sırası|Etkin|Devre dışı|Görsel|Dil|Çevrilmiş ad|Çevrilmiş açıklama|Kaydet|Hediye oluştur|Düzenle|Devre dışı bırak|Tümü|Filtrele|İnceleme gerekli|Platform komisyonu|İşlem ücretleri|Uygulanmaz|Bilinmiyor|Denetim geçmişi|Kalan hediye değerinin iadesini onaylayın:|İncelemeyi kapat|Harici belge / referans|{count} muhasebe hareketi|Geçici: ücretler bilinmiyor|Brüt ödemeler|Alıcı kazançları|Net platform geliri|Cüzdan hediye hacmi|PayPal hediye hacmi|İadeler|Popüler hediyeler|Desteklenen etkinlikler|Alıcılar|Onayla|Reddet|Tamamlanan ödemeyi kaydet',
        samples: 'Kahve fincanı|Pasta dilimi|Gül|Hediye kutusu|Altın kupa',
    },
    fa: {
        core: 'ارسال حمایت|یک هدیه انتخاب کنید|گیرنده|تعداد|مجموع|سهم سازنده ۸۵٪ و سهم Scemory برابر ۱۵٪ است.|موجودی کیف پول|روش پرداخت|پرداخت از کیف پول|پرداخت با PayPal|تأیید حمایت|هدیه ارسال شد|پرداخت تأیید نشد. با همان انتخاب دوباره تلاش کنید.|موجودی ناکافی یا ارز ناسازگار است.|هدایا بارگیری نشدند. دوباره تلاش کنید.|هنوز هدیه‌ای نیست.|بستن|در دسترس نیست|بررسی پرداخت|هدایا و درآمدها|هدایای ارسالی|هدایای دریافتی|وضعیت پرداخت|درآمد سازنده|درآمدها به مدت {days} روز نگه داشته می‌شوند.|درخواست برداشت|مبلغ|ارز|مقصد پرداخت|برداشت‌ها|لغو|برداشت انجام نشد. درآمد قابل برداشت را بررسی کنید یا با پشتیبانی تماس بگیرید.|{count} هدیه',
        states: 'در انتظار|پرداخت شده|ناموفق|لغو شده|بخشی بازپرداخت شده|بازپرداخت شده|برگشت داده شده|قابل برداشت|رزرو برای پرداخت|تسویه شده|بدهی قابل بازیابی|تأیید شده|رد شده',
        admin: 'هدایای مجازی|ذخیره یا بررسی انجام نشد. اطلاعات را بررسی و دوباره تلاش کنید.|فهرست هدایا|تراکنش‌ها|گزارش مالی|نام|کد هدیه|توضیحات|قیمت|ترتیب نمایش|فعال|غیرفعال|تصویر|زبان|نام ترجمه‌شده|توضیحات ترجمه‌شده|ذخیره|ایجاد هدیه|ویرایش|غیرفعال کردن|همه|فیلتر|نیازمند بررسی|کمیسیون پلتفرم|هزینه پردازش|کاربرد ندارد|نامشخص|سابقه حسابرسی|بازپرداخت ارزش باقی‌مانده هدیه را تأیید کنید:|پایان بررسی|مدرک یا مرجع خارجی|{count} حرکت دفتر حساب|موقت: هزینه‌ها نامشخص|پرداخت‌های ناخالص|درآمد گیرندگان|درآمد خالص پلتفرم|حجم هدایای کیف پول|حجم هدایای PayPal|بازپرداخت‌ها|هدایای محبوب|رویدادهای حمایت‌شده|گیرندگان|تأیید|رد|ثبت پرداخت تکمیل‌شده',
        samples: 'فنجان قهوه|برش کیک|گل رز|جعبه هدیه|جام طلایی',
    },
    ur: {
        core: 'تعاون بھیجیں|تحفہ منتخب کریں|وصول کنندہ|تعداد|کل رقم|تخلیق کار کو 85٪ ملتا ہے اور Scemory کو 15٪۔|والیٹ بیلنس|ادائیگی کا طریقہ|والیٹ سے ادائیگی|PayPal سے ادائیگی|تعاون بھیجنے کی تصدیق|تحفہ بھیج دیا گیا|ادائیگی کی تصدیق نہیں ہوئی۔ اسی انتخاب کے ساتھ دوبارہ کوشش کریں۔|بیلنس ناکافی ہے یا کرنسی مختلف ہے۔|تحائف لوڈ نہیں ہوئے۔ دوبارہ کوشش کریں۔|ابھی کوئی تحفہ نہیں۔|بند کریں|دستیاب نہیں|ادائیگی کی تصدیق|تحائف اور آمدنی|بھیجے گئے تحائف|موصول ہونے والے تحائف|ادائیگی کی حالت|تخلیق کار کی آمدنی|آمدنی {days} دن تک روکی جاتی ہے۔|رقم نکلوانے کی درخواست|رقم|کرنسی|ادائیگی کی منزل|رقم نکلوانے کی درخواستیں|منسوخ|رقم نہیں نکالی جا سکی۔ دستیاب آمدنی دیکھیں یا مدد سے رابطہ کریں۔|{count} تحائف',
        states: 'زیر التوا|ادا شدہ|ناکام|منسوخ شدہ|جزوی واپسی|واپس کیا گیا|معکوس|دستیاب|ادائیگی کے لیے محفوظ|تصفیہ شدہ|واجب وصولی|منظور شدہ|مسترد',
        admin: 'مجازی تحائف|محفوظ یا تصدیق نہیں ہو سکی۔ معلومات دیکھ کر دوبارہ کوشش کریں۔|کیٹلاگ|لین دین|مالی رپورٹ|نام|تحفے کا کوڈ|تفصیل|قیمت|نمائش کی ترتیب|فعال|غیر فعال|تصویر|زبان|ترجمہ شدہ نام|ترجمہ شدہ تفصیل|محفوظ کریں|تحفہ بنائیں|ترمیم|غیر فعال کریں|سب|فلٹر|جائزہ درکار|پلیٹ فارم کمیشن|پروسیسنگ فیس|لاگو نہیں|نامعلوم|آڈٹ کی تاریخ|تحفے کی باقی قیمت کی واپسی کی تصدیق کریں:|جائزہ مکمل کریں|بیرونی ثبوت یا حوالہ|{count} حسابی اندراجات|عارضی: فیس نامعلوم|مجموعی ادائیگیاں|وصول کنندگان کی آمدنی|پلیٹ فارم کی خالص آمدنی|والیٹ تحائف کی مالیت|PayPal تحائف کی مالیت|واپس کی گئی رقوم|مقبول تحائف|تعاون یافتہ تقریبات|وصول کنندگان|منظور کریں|مسترد کریں|مکمل ادائیگی درج کریں',
        samples: 'کافی کا کپ|کیک کا ٹکڑا|گلاب|تحفے کا ڈبہ|سنہری ٹرافی',
    },
    hi: {
        core: 'सहयोग भेजें|उपहार चुनें|प्राप्तकर्ता|संख्या|कुल राशि|निर्माता को 85% मिलता है; Scemory 15% रखता है।|वॉलेट शेष|भुगतान का तरीका|वॉलेट से भुगतान|PayPal से भुगतान|सहयोग की पुष्टि करें|उपहार भेजा गया|भुगतान सत्यापित नहीं हुआ। उसी चयन के साथ फिर कोशिश करें।|अपर्याप्त शेष या मुद्रा अलग है।|उपहार लोड नहीं हुए। फिर कोशिश करें।|अभी कोई उपहार नहीं।|बंद करें|उपलब्ध नहीं|भुगतान सत्यापित करें|उपहार और कमाई|भेजे गए उपहार|प्राप्त उपहार|भुगतान की स्थिति|निर्माता की कमाई|कमाई {days} दिनों तक रोकी जाती है।|निकासी का अनुरोध|राशि|मुद्रा|भुगतान का गंतव्य|निकासी|रद्द करें|निकासी नहीं हो सकी। उपलब्ध कमाई देखें या सहायता से संपर्क करें।|{count} उपहार',
        states: 'लंबित|भुगतान हुआ|विफल|रद्द|आंशिक वापसी|वापस किया गया|उलटा गया|उपलब्ध|भुगतान के लिए आरक्षित|निपटान हुआ|वसूली बकाया|स्वीकृत|अस्वीकृत',
        admin: 'आभासी उपहार|सहेजना या सत्यापन विफल हुआ। विवरण देखकर फिर कोशिश करें।|कैटलॉग|लेन-देन|वित्तीय रिपोर्ट|नाम|उपहार कोड|विवरण|कीमत|प्रदर्शन क्रम|सक्रिय|निष्क्रिय|चित्र|भाषा|अनूदित नाम|अनूदित विवरण|सहेजें|उपहार बनाएँ|संपादित करें|निष्क्रिय करें|सभी|फ़िल्टर|समीक्षा आवश्यक|प्लेटफ़ॉर्म कमीशन|प्रसंस्करण शुल्क|लागू नहीं|अज्ञात|ऑडिट इतिहास|उपहार के शेष मूल्य की वापसी की पुष्टि करें:|समीक्षा पूरी करें|बाहरी प्रमाण / संदर्भ|{count} लेखा प्रविष्टियाँ|अस्थायी: शुल्क अज्ञात|सकल भुगतान|प्राप्तकर्ताओं की कमाई|प्लेटफ़ॉर्म की शुद्ध आय|वॉलेट उपहारों का मूल्य|PayPal उपहारों का मूल्य|वापसी|लोकप्रिय उपहार|समर्थित आयोजन|प्राप्तकर्ता|स्वीकार करें|अस्वीकार करें|पूरा भुगतान दर्ज करें',
        samples: 'कॉफ़ी कप|केक का टुकड़ा|गुलाब|उपहार बॉक्स|सुनहरी ट्रॉफ़ी',
    },
    ja: {
        core: '応援を送る|ギフトを選ぶ|受取人|数量|合計|クリエイターに85%、Scemoryに15%が配分されます。|ウォレット残高|支払い方法|ウォレットで支払う|PayPalで支払う|応援を確定|ギフトを送信しました|支払いを確認できません。同じ選択で再試行してください。|残高不足または通貨が一致しません。|ギフトを読み込めません。再試行してください。|ギフトはまだありません。|閉じる|利用できません|支払いを確認|ギフトと収益|送ったギフト|受け取ったギフト|支払い状況|クリエイター収益|収益は{days}日間保留されます。|出金を申請|金額|通貨|送金先|出金|キャンセル|出金できません。利用可能な収益を確認するかサポートにお問い合わせください。|ギフト{count}件',
        states: '保留中|支払い済み|失敗|キャンセル済み|一部返金済み|返金済み|取消済み|利用可能|出金予約済み|送金完了|回収が必要|承認済み|却下済み',
        admin: 'バーチャルギフト|保存または確認に失敗しました。内容を確認して再試行してください。|カタログ|取引|財務レポート|名前|ギフトコード|説明|価格|表示順|有効|無効|画像|言語|翻訳された名前|翻訳された説明|保存|ギフトを作成|編集|無効にする|すべて|絞り込み|要確認|プラットフォーム手数料|決済処理手数料|対象外|不明|監査履歴|ギフトの残額の返金を確認：|確認を完了|外部証明・参照番号|台帳記録{count}件|暫定：手数料不明|総支払い額|受取人の収益|プラットフォーム純収益|ウォレットギフト取引額|PayPalギフト取引額|返金|人気のギフト|応援されたイベント|受取人|承認|却下|完了した送金を記録',
        samples: 'コーヒーカップ|ケーキ|バラ|ギフトボックス|金のトロフィー',
    },
    zh: {
        core: '发送支持|选择礼物|收礼人|数量|总金额|创作者获得85%，Scemory保留15%。|钱包余额|付款方式|使用钱包付款|使用PayPal付款|确认支持|礼物已发送|无法验证付款。请使用相同选项重试。|余额不足或钱包币种不符。|无法加载礼物，请重试。|暂无礼物。|关闭|不可用|验证付款|礼物与收益|送出的礼物|收到的礼物|付款状态|创作者收益|收益将保留{days}天后释放。|申请提现|金额|币种|收款账户|提现|取消|无法提现。请检查可用收益或联系客服。|{count}份礼物',
        states: '待处理|已付款|失败|已取消|部分退款|已退款|已冲正|可用|已预留付款|已结算|待追回|已批准|已拒绝',
        admin: '虚拟礼物|无法保存或验证，请检查信息后重试。|礼物目录|交易|财务报告|名称|礼物代码|描述|价格|显示顺序|启用|停用|图片|语言|翻译名称|翻译描述|保存|创建礼物|编辑|停用|全部|筛选|需要审核|平台佣金|支付处理费用|不适用|未知|审计记录|确认退还礼物的剩余金额：|完成审核|外部凭证或参考号|{count}笔账目变动|暂定：手续费未知|支付总额|收礼人收益|平台净收入|钱包礼物交易额|PayPal礼物交易额|退款|热门礼物|获得支持的活动|收礼人|批准|拒绝|记录已完成的付款',
        samples: '咖啡杯|蛋糕切片|玫瑰|礼盒|金色奖杯',
    },
};

const messages = Object.fromEntries(Object.entries(rows).map(([locale, row]) => {
    const loading = { en: 'Loading…', ar: 'جارٍ التحميل…', fr: 'Chargement…', de: 'Wird geladen…', es: 'Cargando…', it: 'Caricamento…', ru: 'Загрузка…', tr: 'Yükleniyor…', fa: 'در حال بارگیری…', ur: 'لوڈ ہو رہا ہے…', hi: 'लोड हो रहा है…', ja: '読み込み中…', zh: '正在加载…' };
    const translated = { loading: loading[locale], previous: weekly[locale].previous, next: weekly[locale].next };
    for (const [group, keys] of Object.entries(groups)) {
        const values = row[group].split('|');
        if (values.length !== keys.length) throw new Error(`Invalid gift translations: ${locale}/${group}`);
        keys.forEach((key, index) => { translated[key] = values[index]; });
    }
    return [locale, translated];
}));
// Earnings labels are distinct from generic payment states.
messages.ar.pendingEarnings = 'الأرباح المعلقة';
messages.ar.availableEarnings = 'الأرباح المتاحة';
for (const message of Object.values(messages)) {
    message.pendingEarnings ||= `${message.earnings}: ${message.pending}`;
    message.availableEarnings ||= `${message.earnings}: ${message.available}`;
}
const refundLabels = { en: 'Refund', ar: 'استرداد', fr: 'Rembourser', de: 'Erstatten', es: 'Reembolsar', it: 'Rimborsa', ru: 'Вернуть средства', tr: 'İade et', fa: 'بازپرداخت', ur: 'رقم واپس کریں', hi: 'वापस करें', ja: '返金する', zh: '退款' };
for (const [locale, message] of Object.entries(messages)) message.refund = refundLabels[locale];
const priceChanged = {
    en: 'The price changed. Select the gift again to confirm its current price.',
    ar: 'تغير السعر. اختر الهدية مجددًا لتأكيد سعرها الحالي.',
    fr: 'Le prix a changé. Sélectionnez à nouveau le cadeau pour confirmer son prix actuel.',
    de: 'Der Preis hat sich geändert. Wähle das Geschenk erneut, um den aktuellen Preis zu bestätigen.',
    es: 'El precio cambió. Vuelve a elegir el regalo para confirmar el precio actual.',
    it: 'Il prezzo è cambiato. Seleziona di nuovo il regalo per confermare il prezzo attuale.',
    ru: 'Цена изменилась. Выберите подарок заново, чтобы подтвердить текущую цену.',
    tr: 'Fiyat değişti. Güncel fiyatı onaylamak için hediyeyi yeniden seçin.',
    fa: 'قیمت تغییر کرده است. هدیه را دوباره انتخاب کنید تا قیمت فعلی تأیید شود.',
    ur: 'قیمت بدل گئی ہے۔ موجودہ قیمت کی تصدیق کے لیے تحفہ دوبارہ منتخب کریں۔',
    hi: 'कीमत बदल गई है। मौजूदा कीमत की पुष्टि के लिए उपहार फिर चुनें।',
    ja: '価格が変わりました。ギフトを選び直し、現在の価格を確認してください。',
    zh: '价格已更改。请重新选择礼物以确认当前价格。',
};
for (const [locale, message] of Object.entries(messages)) message.priceChanged = priceChanged[locale];
export default messages;
