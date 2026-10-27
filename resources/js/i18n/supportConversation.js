const keys = 'tabsLabel|newMessage|myConversations|retry|loading|open|pending|resolved|unread|olderMessages|supportTeam|you|customer|reply|sendReply|sending|empty|loadError|sendError|subjectTitle|assignToMe|all|search'.split('|');

const lines = {
  en: 'Support navigation|New Message|My Conversations|Retry|Loading…|Open|Pending|Resolved|Unread|Older messages|Support team|You|Customer|Reply|Send reply|Sending…|No conversations yet.|Could not load conversations.|Could not send your reply.|Conversation title|Assign to me|All|Search',
  ar: 'التنقل في الدعم|رسالة جديدة|محادثاتي|إعادة المحاولة|جارٍ التحميل…|مفتوحة|بانتظار الرد|تم الحل|غير مقروءة|رسائل أقدم|فريق الدعم|أنت|العميل|الرد|إرسال الرد|جارٍ الإرسال…|لا توجد محادثات بعد.|تعذر تحميل المحادثات.|تعذر إرسال ردك.|عنوان المحادثة|إسناد إليّ|الكل|بحث',
  fr: 'Navigation du support|Nouveau message|Mes conversations|Réessayer|Chargement…|Ouvert|En attente|Résolu|Non lu|Messages précédents|Équipe de support|Vous|Client|Répondre|Envoyer la réponse|Envoi…|Aucune conversation.|Impossible de charger les conversations.|Impossible d’envoyer votre réponse.|Titre de la conversation|Me l’attribuer|Tous|Rechercher',
  de: 'Supportnavigation|Neue Nachricht|Meine Unterhaltungen|Erneut versuchen|Lädt…|Offen|Ausstehend|Gelöst|Ungelesen|Ältere Nachrichten|Supportteam|Sie|Kunde|Antwort|Antwort senden|Wird gesendet…|Noch keine Unterhaltungen.|Unterhaltungen konnten nicht geladen werden.|Ihre Antwort konnte nicht gesendet werden.|Titel der Unterhaltung|Mir zuweisen|Alle|Suchen',
  ru: 'Навигация поддержки|Новое сообщение|Мои обращения|Повторить|Загрузка…|Открыто|Ожидает ответа|Решено|Не прочитано|Ранние сообщения|Служба поддержки|Вы|Клиент|Ответ|Отправить ответ|Отправка…|Обращений пока нет.|Не удалось загрузить обращения.|Не удалось отправить ответ.|Название обращения|Назначить мне|Все|Поиск',
  es: 'Navegación de soporte|Nuevo mensaje|Mis conversaciones|Reintentar|Cargando…|Abierto|Pendiente|Resuelto|Sin leer|Mensajes anteriores|Equipo de soporte|Tú|Cliente|Responder|Enviar respuesta|Enviando…|Aún no hay conversaciones.|No se pudieron cargar las conversaciones.|No se pudo enviar tu respuesta.|Título de la conversación|Asignármelo|Todos|Buscar',
  it: 'Navigazione supporto|Nuovo messaggio|Le mie conversazioni|Riprova|Caricamento…|Aperto|In attesa|Risolto|Non letto|Messaggi precedenti|Team di supporto|Tu|Cliente|Rispondi|Invia risposta|Invio…|Nessuna conversazione.|Impossibile caricare le conversazioni.|Impossibile inviare la risposta.|Titolo della conversazione|Assegna a me|Tutti|Cerca',
  hi: 'सहायता नेविगेशन|नया संदेश|मेरी बातचीत|फिर कोशिश करें|लोड हो रहा है…|खुला|लंबित|हल हो गया|अपठित|पुराने संदेश|सहायता टीम|आप|ग्राहक|उत्तर|उत्तर भेजें|भेजा जा रहा है…|अभी कोई बातचीत नहीं।|बातचीत लोड नहीं हो सकी।|आपका उत्तर भेजा नहीं जा सका।|बातचीत का शीर्षक|मुझे सौंपें|सभी|खोजें',
  ja: 'サポートナビゲーション|新しいメッセージ|自分の会話|再試行|読み込み中…|受付中|返信待ち|解決済み|未読|以前のメッセージ|サポートチーム|あなた|お客様|返信|返信を送信|送信中…|会話はまだありません。|会話を読み込めませんでした。|返信を送信できませんでした。|会話の件名|自分に割り当てる|すべて|検索',
  fa: 'ناوبری پشتیبانی|پیام جدید|گفتگوهای من|تلاش دوباره|در حال بارگذاری…|باز|در انتظار|حل‌شده|خوانده‌نشده|پیام‌های قدیمی‌تر|تیم پشتیبانی|شما|مشتری|پاسخ|ارسال پاسخ|در حال ارسال…|هنوز گفتگویی وجود ندارد.|بارگذاری گفتگوها ممکن نشد.|ارسال پاسخ ممکن نشد.|عنوان گفتگو|واگذاری به من|همه|جستجو',
  zh: '支持导航|新消息|我的会话|重试|加载中…|处理中|待回复|已解决|未读|更早的消息|支持团队|您|客户|回复|发送回复|发送中…|暂无会话。|无法加载会话。|无法发送回复。|会话标题|分配给我|全部|搜索',
  ur: 'معاونت کی رہنمائی|نیا پیغام|میری گفتگوئیں|دوبارہ کوشش کریں|لوڈ ہو رہا ہے…|کھلا|زیر التوا|حل شدہ|ان پڑھ|پرانے پیغامات|معاونتی ٹیم|آپ|صارف|جواب|جواب بھیجیں|بھیجا جا رہا ہے…|ابھی کوئی گفتگو نہیں۔|گفتگوئیں لوڈ نہیں ہو سکیں۔|آپ کا جواب نہیں بھیجا جا سکا۔|گفتگو کا عنوان|مجھے تفویض کریں|سب|تلاش',
  tr: 'Destek gezinmesi|Yeni Mesaj|Görüşmelerim|Tekrar dene|Yükleniyor…|Açık|Beklemede|Çözüldü|Okunmamış|Eski mesajlar|Destek ekibi|Siz|Müşteri|Yanıtla|Yanıt gönder|Gönderiliyor…|Henüz görüşme yok.|Görüşmeler yüklenemedi.|Yanıtınız gönderilemedi.|Görüşme başlığı|Bana ata|Tümü|Ara',
};

export default Object.fromEntries(Object.entries(lines).map(([locale, line]) => {
  const values = line.split('|');
  return [locale, Object.fromEntries(keys.map((key, index) => [key, values[index]]))];
}));
