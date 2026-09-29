/* admin.html mantiqi (avval sahifa ichida edi). O'zgartirilsa, admin.html'dagi ?v= raqamini oshiring. */
// PHP backend'ning ochiq API manzili (backend/public/index.php) — sahifa qayerdan
// ochilgan bo'lsa (localhost, mahalliy tarmoq IP'si, ngrok, haqiqiy domen), o'sha
// joyning o'ziga nisbatan avtomatik hisoblanadi, qo'lda o'zgartirish shart emas.
const API_BASE_URL = new URL('backend/public/index.php', window.location.href).href;

// Tarmoq beqarorligi tufayli vaqtincha muvaffaqiyatsiz bo'lgan so'rovni qayta urinadi.
async function apiCall(payload){
  const body = JSON.stringify(payload);
  let lastErr;
  for(let attempt=0; attempt<3; attempt++){
    try{
      const res = await fetch(API_BASE_URL, { method:'POST', headers:{ 'Content-Type':'application/json' }, body, credentials:'same-origin' });
      const text = await res.text();
      try{
        const parsed = JSON.parse(text);
        if(parsed && parsed.success === false && parsed.code === 'SESSION_EXPIRED'){
          try{ sessionStorage.removeItem('km_user'); }catch(e){}
          window.location.href = 'login.html';
        }
        return parsed;
      }
      catch(parseErr){
        lastErr = new Error("Serverdan noto'g'ri javob keldi");
        if(attempt < 2){ await new Promise(r=>setTimeout(r, 500)); continue; }
        throw lastErr;
      }
    }catch(networkErr){
      lastErr = networkErr;
      if(attempt < 2){ await new Promise(r=>setTimeout(r, 500)); continue; }
      throw lastErr;
    }
  }
}

let currentLang = localStorage.getItem('km_lang') || 'uz';
const dict = {
  uz:{ brandName:"\"O'zbektelekom\" AK", eyebrow:"Admin", pageTitle:"Statistika",
    hubAnticor:"Korrupsiyaga qarshi kurashish", hubAnticorDesc:"Hujjat va test statistikasi",
    hubEmployeesMain:"Xodimlar", hubEmployeesMainDesc:"Xodimlar ro'yxati va yangi xodim qo'shish",
    hubConflict:"Manfaatlar to'qnashuvi", hubConflictDesc:"Manfaatlar to'qnashuvi bo'yicha deklaratsiyalar",
    soonMsg:"Bu bo'lim tez orada ishga tushiriladi",
    hubEmployees:"Xodimlar ro'yxati", hubEmployeesDesc:"Xodimlar bo'yicha umumiy ma'lumot",
    hubTests:"Test savollari", hubTestsDesc:"Test savollarini qo'shish, tahrirlash, o'chirish",
    hubDocs:"Hujjat qo'shish", hubDocsDesc:"Normativ hujjatlarni qo'shish, tahrirlash, o'chirish",
    soonTag:"Tez orada", backBtn:"Ortga", cancelBtn:"Bekor qilish", downloadBtn:"Hisobotni yuklab olish",
    hubHrDocs:"Yuborilgan hujjatlar", hubHrDocsDesc:"Xodimlar Inson resurslariga yuborgan hujjatlar",
    confirmDeleteHrDoc:"Ushbu hujjatni butunlay o'chirmoqchimisiz?",
    hubPurchasesMain:"Xaridlar", hubPurchasesMainDesc:"Xaridlar reyestri va reyestrga kiritish",
    hubPurchasesRegistry:"Xaridlar reyestri", hubPurchasesRegistryDesc:"Barcha kiritilgan xaridlar ro'yxati",
    hubPurchaseEntry:"Reyestrga kiritish", hubPurchaseEntryDesc:"Yangi xarid (shartnoma) yozuvini kiritish",
    pShartnomaSanaLabel:"Shartnoma sanasi", pShartnomaRaqamiLabel:"Shartnoma raqami", pKontragentLabel:"Kontragent",
    pShartnomaPredmetiLabel:"Shartnoma predmeti", pShartnomaSummasiLabel:"Shartnoma summasi", pXaridTuriLabel:"Xarid turi",
    pIzohLabel:"Izoh", pFileLabel:"Shartnoma fayli (PDF)", purchaseSearchPh:"Shartnoma raqami yoki kontragent bo'yicha qidirish...",
    purchaseExportBtn:"Excel'ga yuklab olish", thTartibRaqam:"T/r", thFile:"Fayl",
    purchaseFileRequiredErr:"Shartnoma faylini (PDF) yuklang",
    xlsxLibErr:"Excel fayl yaratish kutubxonasi yuklanmadi. Internet ulanishini tekshirib, sahifani qayta yuklang.",
    hubSupport:"Yordam so'rovlari", hubSupportDesc:"Xodimlardan kelgan murojaatlar",
    hubReports:"Hisobotlar", hubReportsDesc:"Barcha ma'lumotlarni Excel fayl sifatida yuklab olish",
    repUsersTitle:"Xodimlar (Users)", repUsersDesc:"To'liq xodimlar ro'yxati, rol, aloqa ma'lumotlari",
    repProgressTitle:"Progress", repProgressDesc:"Hujjat bilan tanishish va test topshirish tarixi",
    repSupportTitle:"Support", repSupportDesc:"Xodimlardan kelgan murojaatlar va izohlar",
    repTestsTitle:"Test savollari", repTestsDesc:"Barcha test savollari va to'g'ri javoblar",
    repNotifTitle:"Xabarnomalar", repNotifDesc:"Yuborilgan barcha xabarnomalar",
    repNotifReadsTitle:"Xabarnoma o'qilganlik", repNotifReadsDesc:"Kim, qachon o'qigani",
    repSurveyQTitle:"So'rovnoma savollari", repSurveyQDesc:"Anonim so'rovnoma savollari",
    repSurveyATitle:"So'rovnoma javoblari", repSurveyADesc:"Anonim javoblar (foydalanuvchi bilan bog'lanmagan)",
    repTestAttemptsTitle:"Test natijalari", repTestAttemptsDesc:"Xodimlar topshirgan test natijalari — sinov/namoyish yozuvlarini o'chirish mumkin",
    repDocReadsTitle:"Hujjat bilan tanishish", repDocReadsDesc:"Xodimlar hujjatni o'qidim deb belgilagan yozuvlar — sinov/namoyish yozuvlarini o'chirish mumkin",
    viewBtn:"Ko'rish / O'chirish", deleteSelectedBtn:"Tanlanganlarni o'chirish",
    confirmBulkDelete:"Tanlangan {n} ta yozuvni butunlay o'chirmoqchimisiz? Bu amalni orqaga qaytarib bo'lmaydi.",
    colBall:"Ball", colFoiz:"Foiz", colNatija:"Natija", colOtdi:"O'tdi", colOtmadi:"O'tmadi",
    colJavoblarSoni:"Javoblar soni", colMatn:"Matn", colYuboruvchi:"Yuboruvchi", colXabarId:"Xabar ID", colOqilganSana:"O'qilgan sana",
    complianceReportBtn:"Bajarilmaganlar hisoboti",
    complianceBothSheet:"Ikkalasi ham bajarilgan", complianceNoDocSheet:"Hujjat bilan tanishmagan", complianceNoTestSheet:"Test topshirmagan",
    supportIzohLabel:"Izoh", supportIzohPh:"Murojaatga izoh/javob yozing",
    thMurojaat:"Murojaat", thSana:"Sana", supportDetailTitle:"Murojaat tafsiloti",
    supportCommentsTitle:"Izohlar tarixi", supportNoComments:"Hozircha izoh yo'q",
    supportAddCommentBtn:"Izoh qo'shish",
    hubNotif:"Xabarnoma yuborish", hubNotifDesc:"Xodimlarga xabarnoma yuborish va hisobot",
    notifTargetTypeLabel:"Qamrov", notifTargetUsers:"Foydalanuvchilar", notifTargetDept:"Bo'linma",
    notifUsersLabel:"Xodimlarni tanlang", notifDeptLabel:"Bo'linmani tanlang",
    notifTextLabel:"Xabar matni", notifTextPh:"Xabarnoma matnini kiriting", notifSendBtn:"Yuborish",
    notifNoTarget:"Kamida bitta qabul qiluvchi tanlang", notifHistoryTitle:"Yuborilgan xabarnomalar",
    notifNoHistory:"Hozircha xabarnoma yuborilmagan", notifReadStat:"O'qigan", notifNoReaders:"Hali hech kim o'qimagan",
    saveBtn:"Saqlash", savedOk:"Saqlandi ✓", savedErr:"Xatolik yuz berdi, qayta urinib ko'ring",
    totalLbl:"Jami xodimlar", docsLbl:"Hujjat bilan tanishgan", passLbl:"Testdan o'tgan", failLbl:"O'tmagan", waitLbl:"Boshlamagan",
    thId:"ID", thFish:"F.I.Sh", thPos:"Lavozim", thDept:"Bo'linma", thPhone:"Telefon", thDoc:"Hujjat sanasi", thTest:"Test natijasi",
    thLogin:"Login", thRol:"Rol", thAmal:"Amal",
    searchPh:"F.I.Sh bo'yicha qidirish...", noResults:"Hech narsa topilmadi",
    loading:"Yuklanmoqda...", yes:"Ha", no:"Yo'q", notTaken:"Ishlamagan", certDownloadBtn:"Sertifikatni yuklab olish", testAttemptsHint:"Natija · ishlatilgan / ruxsat etilgan urinishlar", failFilterHint:"Bosing — faqat testdan o'ta olmaganlarni ko'rsatish", allFilials:"Barcha filiallar", reminderBtn:"Eslatma yuborish", reminderTitle:"Eslatma yuborish", reminderScope:"Qamrov: {f} (filialni yuqoridagi ro'yxatdan o'zgartirish mumkin). Xodimlarga bildirishnoma boradi.", reminderTest:"Testni topshirmaganlarga", reminderDoc:"Hujjat bilan tanishmaganlarga", reminderSend:"Yuborish", reminderPickType:"Kamida bitta turni tanlang", reminderConfirm:"Tanlangan xodimlarga eslatma yuborilsinmi?", reminderSent:"Eslatma yuborildi: {n} ta xodimga", testSettingsTitle:"Muddatlar", testDeadlineLabel:"Testni topshirishning oxirgi muddati", testDeadlineHint:"Bo'sh qoldirilsa — muddat belgilanmaydi. Muddat xodimlarga test sahifasida va eslatmalarda ko'rsatiladi.", certValidityLabel:"Sertifikat amal qilish muddati (oy)", certValidityHint:"Masalan 12 — yillik qayta attestatsiya: muddati tugagan xodim testni qaytadan topshiradi (yana 1 ta urinish). 0 — sertifikat muddatsiz.", deadlineLeft:"Topshirish muddati: {d} ({n} kun qoldi)", deadlinePassed:"Topshirish muddati: {d} — o'tib ketgan", validityInfo:"Sertifikat {n} oy amal qiladi", renewalBadge:"Qayta attestatsiya", renewalHint:"Sertifikat muddati tugagan — xodim testni qaytadan topshirishi kerak", validUntilLbl:"{d} gacha amal qiladi", certColValid:"Amal qiladi", noFilial:"Filial ko'rsatilmagan", totalRow:"Jami", filialSummaryBtn:"Filiallar kesimida", colBajarilish:"Bajarilish (o'tganlar)", failFilterOff:"Ro'yxatni ko'rish ›", failFilterOn:"✓ Faqat shular ko'rsatilmoqda", retakeGrantBtn:"Qayta topshirishga ruxsat berish", retakePendingBadge:"Ruxsat berilgan", retakePendingHint:"Qayta topshirishga ruxsat berilgan — xodim hali qayta topshirmagan", retakeConfirm:"{fish} ga testni qayta topshirishga ruxsat berilsinmi? Xodimga xabarnoma yuboriladi.", denied:"Sizda bu sahifaga kirish huquqi yo'q",
    notifTitle:"Xabarnomalar", notifEmpty:"Hozircha xabarnoma yo'q",
    testFormAdd:"Yangi savol qo'shish", testFormEdit:"Savolni tahrirlash", testListTitle:"Mavjud savollar",
    langUzTitle:"O'zbekcha", langRuTitle:"Русский",
    qSavol:"Savol", qVariantA:"Variant A", qVariantB:"Variant B", qVariantC:"Variant C", qVariantD:"Variant D",
    qCorrect:"To'g'ri javob", qCorrectPh:"Variantni tanlang",
    qSavolRu:"Вопрос", qVariantARu:"Ответ А", qVariantBRu:"Ответ Б", qVariantCRu:"Ответ В", qVariantDRu:"Ответ Г", qCorrectRu:"Правильный ответ",
    docFormAdd:"Yangi hujjat qo'shish", docFormEdit:"Hujjatni tahrirlash", docListTitle:"Mavjud hujjatlar",
    docNameUz:"Hujjat nomi (O'zbek)", docNameRu:"Hujjat nomi (Rus)",
    docFileLabel:"PDF fayl", docFileChoose:"PDF fayl tanlash", docFileHint:"Faqat PDF, maksimal 25MB",
    docFileKeepCurrent:"Fayl tanlanmasa, mavjud fayl saqlanib qoladi",
    docFromFolderBadge:"Hujjatlar papkasidan", docFromFolderHint:"Bu hujjat Hujjatlar papkasidan avtomatik olinadi — bu yerda faqat nomini o'zgartirish mumkin; faylni almashtirish yoki o'chirish uchun uni o'sha papkada almashtiring/o'chiring",
    docFileTooLargeErr:"Fayl hajmi juda katta (maksimal 25MB)",
    docFileTypeErr:"Fayl formati noto'g'ri, faqat PDF qabul qilinadi",
    docFileRequiredErr:"PDF fayl tanlanishi shart",
    confirmDeleteTest:"Ushbu savolni o'chirmoqchimisiz?", confirmDeleteDoc:"Ushbu hujjatni o'chirmoqchimisiz?",
    deletedOk:"O'chirildi ✓", editBtn:"Tahrirlash", deleteBtn:"O'chirish",
    hubSurvey:"Anonim so'rovnoma", hubSurveyDesc:"So'rovnoma savollari, natijalari va faollik holati",
    hubSurveyQuestions:"So'rovnoma savollari", hubSurveyQuestionsDesc:"Savol qo'shish, tahrirlash, o'chirish",
    hubSurveyResults:"So'rovnoma natijalari", hubSurveyResultsDesc:"Anonim umumiy statistika",
    surveyFormAdd:"Yangi savol qo'shish", surveyFormEdit:"Savolni tahrirlash", surveyListTitle:"Mavjud savollar",
    confirmDeleteSurvey:"Ushbu savolni o'chirmoqchimisiz?",
    testActiveLabel:"Test holati", surveyActiveLabel:"So'rovnoma holati", activeOn:"Faol — xodimlar ko'ra oladi", activeOff:"Faol emas — 'yakunlangan' ko'rinadi",
    resultsEmpty:"Hozircha javoblar yo'q", totalResponsesLbl:"ta javob",
    qTuri:"Savol turi", qTuriTanlov:"Tanlov (A, B, C, D)", qTuriYulduz:"Yulduzcha bilan baholash", qTuriMatn:"Erkin javob (matn)", qStars:"Yulduzlar soni",
    totalSubmissionsLbl:"Jami ishtirokchilar", totalQuestionsLbl:"Savollar soni", byQuestionLbl:"Javoblar taqsimoti", avgScoreLbl:"O'rtacha ball",
    empFormAdd:"Yangi xodim qo'shish", empLoginLabel:"Login", empPassLabel:"Parol",
    empFamiliyaLabel:"Familiya", empIsmLabel:"Ism", empOtasiLabel:"Otasining ismi",
    empTugilganSanaLabel:"Tug'ilgan sana", thTugilganSana:"Tug'ilgan sana",
    empLavozimLabel:"Lavozim (O'zbek)", empLavozimRuLabel:"Lavozim (Rus)",
    empBolinmaLabel:"Bo'linma (O'zbek)", empBolinmaRuLabel:"Bo'linma (Rus)",
    empFilialLabel:"Filial", thFilial:"Filial", filialNoneOpt:"— Tanlanmagan —",
    filial_ijroiya_apparati:"Ijroiya apparati",
    filial_markaziy:"Markaziy filial",
    filial_shimoliy:"Shimoliy filial",
    filial_sharqiy:"Sharqiy filial",
    filial_janubiy:"Janubiy filial",
    filial_garbiy:"G'arbiy filial",
    filial_janubi_garbiy:"Janubi-G'arbiy filial",
    filial_texnik:"Ixtisoslashtirilgan texnik filial",
    filial_tms_hub:"\"TMS Hub\" filiali",
    hubAddEmployee:"Yangi xodim qo'shish", hubAddEmployeeDesc:"Tizimga yangi xodim yoki administrator qo'shish",
    empFormEdit:"Xodimni tahrirlash", empPassEditHint:"Bo'sh qoldirsangiz, parol o'zgarmaydi",
    confirmDeleteEmployee:"Ushbu xodimni butunlay o'chirmoqchimisiz? Uning barcha tarixi (test, hujjat, so'rovnoma) ham o'chadi.",
    cannotDeleteSelfErr:"O'zingizni o'chira olmaysiz", lastGlAdminErr:"Tizimda kamida bitta bosh administrator qolishi shart",
    role_user:"Xodim", role_anticor_admin:"Anticor — Admin", role_anticor:"Anticor",
    role_super_admin:"Super-admin",
    empSavedOk:"Xodim muvaffaqiyatli qo'shildi ✓", empUpdatedOk:"Xodim ma'lumotlari saqlandi ✓", loginTakenErr:"Ushbu login band, iltimos qayta kiriting",
    weakPasswordErr:"Parol kamida 8 belgidan iborat bo'lib, kamida bitta katta harf, kichik harf, raqam va maxsus belgini o'z ichiga olishi kerak",
    pwnedPasswordErr:"Bu parol avval ma'lumotlar sizib chiqishlarida uchragan. Iltimos boshqa parol tanlang.",
    passwordPolicyHint:"Kamida 8 belgi: katta harf, kichik harf, raqam va maxsus belgi (masalan !@#$%)",
    lockedBadge:"Bloklangan", lockedUntilLabel:"Blok tugash vaqti", unlockBtn:"Blokdan chiqarish",
    hubBackup:"Zaxira nusxa", hubBackupDesc:"Baza va rasmlarning zaxira nusxasini olish va yuklab olish",
    backupDesc:"Baza (MySQL) va profil rasmlarining to'liq nusxasi. Kunlik avtomatik zaxira uchun backend/scripts/backup.php faylini Task Scheduler/cron orqali sozlang (backend/README.md).",
    backupCreateBtn:"Hozir zaxira nusxa olish", backupCreating:"Zaxira olinmoqda...",
    backupColName:"Sana / vaqt", backupColSize:"Hajmi", backupDownloadBtn:"Yuklab olish",
    hubErrorLog:"Tizim jurnali", hubErrorLogDesc:"Admin amallari va server xatoliklari",
    auditTab:"Amallar jurnali", errorsTab:"Server xatoliklari", auditDesc:"Admin amallari: xodim qo'shish/tahrirlash/o'chirish, natijalarni o'chirish, sertifikatlar, sozlamalar va h.k. (so'nggi 2000 ta).", auditSearchPh:"Kim, amal yoki tafsilot bo'yicha qidirish...", auditColWho:"Kim", auditColDetails:"Tafsilot",
    hubCert:"Sertifikat", hubCertDesc:"Sertifikatdagi imzo qo'yuvchilar va namuna",
    repCertTitle:"Sertifikatlar", repCertDesc:"Sertifikat olgan xodimlar — ko'rish va bekor qilish (o'chirish)", colCertNumber:"Sertifikat raqami", colCertPdf:"PDF",
    repCertNote:"O'chirilgan sertifikat bekor qilinadi: xodimda ko'rinmaydi, QR-kod \"amal qilmaydi\" deb ko'rsatadi. Test natijasi saqlanib qoladi. Sertifikat xodim testni qaytadan muvaffaqiyatli topshirsagina yana beriladi (buning uchun Test natijalaridan uning urinishlarini o'chiring).", certSettingsTitle:"Sertifikat sozlamalari",
    certSettingsDesc:"Sertifikat pastidagi imzo qismida xodimning filiali rahbari va komplaens departamenti direktori chiqadi. QR-kod sertifikat haqiqiyligini tekshirish sahifasiga olib boradi. O'zgartirishlar saqlangach, xodimlarning sertifikatlari keyingi ochilishda avtomatik yangilanadi.",
    certColTitle:"Lavozim (sertifikatda)", certColName:"F.I.Sh (masalan: Aliyev A.A.)", certColPreview:"Namuna", certKomplaensRow:"Komplaens departamenti", certPreviewLink:"Ko'rish",
    errorLogDesc:"So'nggi 200 ta server xatoligi (eng yangisi birinchi). 90 kundan eski yozuvlar avtomatik o'chiriladi.",
    errorLogColAction:"Amal", errorLogColMessage:"Xabar" },
  ru:{ brandName:"АО «Узтелеком»", eyebrow:"Админ", pageTitle:"Статистика",
    hubAnticor:"Противодействие коррупции", hubAnticorDesc:"Статистика по документам и тестам",
    hubEmployeesMain:"Сотрудники", hubEmployeesMainDesc:"Список сотрудников и добавление нового сотрудника",
    hubConflict:"Конфликт интересов", hubConflictDesc:"Декларации о конфликте интересов",
    soonMsg:"Этот раздел скоро будет запущен",
    hubEmployees:"Список сотрудников", hubEmployeesDesc:"Общая информация по сотрудникам",
    hubTests:"Тестовые вопросы", hubTestsDesc:"Добавление, редактирование, удаление вопросов",
    hubDocs:"Добавить документ", hubDocsDesc:"Добавление, редактирование, удаление документов",
    soonTag:"Скоро", backBtn:"Назад", cancelBtn:"Отмена", downloadBtn:"Скачать отчёт",
    hubHrDocs:"Полученные документы", hubHrDocsDesc:"Документы, отправленные сотрудниками через раздел кадров",
    confirmDeleteHrDoc:"Удалить этот документ навсегда?",
    hubPurchasesMain:"Закупки", hubPurchasesMainDesc:"Реестр закупок и внесение в реестр",
    hubPurchasesRegistry:"Реестр закупок", hubPurchasesRegistryDesc:"Список всех внесённых закупок",
    hubPurchaseEntry:"Внесение в реестр", hubPurchaseEntryDesc:"Внесение новой записи о закупке (договор)",
    pShartnomaSanaLabel:"Дата договора", pShartnomaRaqamiLabel:"Номер договора", pKontragentLabel:"Контрагент",
    pShartnomaPredmetiLabel:"Предмет договора", pShartnomaSummasiLabel:"Сумма договора", pXaridTuriLabel:"Вид закупки",
    pIzohLabel:"Комментарий", pFileLabel:"Файл договора (PDF)", purchaseSearchPh:"Поиск по номеру договора или контрагенту...",
    purchaseExportBtn:"Скачать в Excel", thTartibRaqam:"№", thFile:"Файл",
    purchaseFileRequiredErr:"Загрузите файл договора (PDF)",
    xlsxLibErr:"Библиотека для создания Excel-файла не загрузилась. Проверьте интернет-соединение и обновите страницу.",
    hubSupport:"Обращения", hubSupportDesc:"Обращения от сотрудников",
    hubReports:"Отчёты", hubReportsDesc:"Скачать все данные в формате Excel",
    repUsersTitle:"Сотрудники (Users)", repUsersDesc:"Полный список сотрудников, роль, контакты",
    repProgressTitle:"Progress", repProgressDesc:"История ознакомления с документами и прохождения теста",
    repSupportTitle:"Support", repSupportDesc:"Обращения сотрудников и комментарии",
    repTestsTitle:"Тестовые вопросы", repTestsDesc:"Все вопросы теста и правильные ответы",
    repNotifTitle:"Уведомления", repNotifDesc:"Все отправленные уведомления",
    repNotifReadsTitle:"Прочтение уведомлений", repNotifReadsDesc:"Кто и когда прочитал",
    repSurveyQTitle:"Вопросы опроса", repSurveyQDesc:"Вопросы анонимного опроса",
    repSurveyATitle:"Ответы опроса", repSurveyADesc:"Анонимные ответы (не связаны с пользователем)",
    repTestAttemptsTitle:"Результаты теста", repTestAttemptsDesc:"Результаты теста сотрудников — тестовые/демо-записи можно удалить",
    repDocReadsTitle:"Ознакомление с документами", repDocReadsDesc:"Отметки об ознакомлении с документом — тестовые/демо-записи можно удалить",
    viewBtn:"Просмотр / Удаление", deleteSelectedBtn:"Удалить выбранные",
    confirmBulkDelete:"Удалить выбранные {n} записей навсегда? Это действие нельзя отменить.",
    colBall:"Баллы", colFoiz:"Процент", colNatija:"Результат", colOtdi:"Прошёл", colOtmadi:"Не прошёл",
    colJavoblarSoni:"Кол-во ответов", colMatn:"Текст", colYuboruvchi:"Отправитель", colXabarId:"ID уведомления", colOqilganSana:"Дата прочтения",
    complianceReportBtn:"Отчёт по невыполнению",
    complianceBothSheet:"Оба выполнены", complianceNoDocSheet:"Не ознакомлен с документом", complianceNoTestSheet:"Не прошёл тест",
    supportIzohLabel:"Комментарий", supportIzohPh:"Напишите комментарий/ответ на обращение",
    thMurojaat:"Обращение", thSana:"Дата", supportDetailTitle:"Детали обращения",
    supportCommentsTitle:"История комментариев", supportNoComments:"Комментариев пока нет",
    supportAddCommentBtn:"Добавить комментарий",
    hubNotif:"Отправить уведомление", hubNotifDesc:"Отправка уведомлений сотрудникам и отчёт",
    notifTargetTypeLabel:"Охват", notifTargetUsers:"Пользователи", notifTargetDept:"Подразделение",
    notifUsersLabel:"Выберите сотрудников", notifDeptLabel:"Выберите подразделение",
    notifTextLabel:"Текст уведомления", notifTextPh:"Введите текст уведомления", notifSendBtn:"Отправить",
    notifNoTarget:"Выберите хотя бы одного получателя", notifHistoryTitle:"Отправленные уведомления",
    notifNoHistory:"Уведомления ещё не отправлялись", notifReadStat:"Прочитали", notifNoReaders:"Пока никто не прочитал",
    saveBtn:"Сохранить", savedOk:"Сохранено ✓", savedErr:"Произошла ошибка, попробуйте снова",
    totalLbl:"Всего сотрудников", docsLbl:"Ознакомились с документами", passLbl:"Прошли тест", failLbl:"Не прошли", waitLbl:"Не начали",
    thId:"ID", thFish:"ФИО", thPos:"Должность", thDept:"Подразделение", thPhone:"Телефон", thDoc:"Дата ознакомления", thTest:"Результат теста",
    thLogin:"Логин", thRol:"Роль", thAmal:"Действие",
    searchPh:"Поиск по ФИО...", noResults:"Ничего не найдено",
    loading:"Загрузка...", yes:"Да", no:"Нет", notTaken:"Не пройден", certDownloadBtn:"Скачать сертификат", testAttemptsHint:"Результат · использовано / разрешено попыток", failFilterHint:"Нажмите — показать только не сдавших тест", allFilials:"Все филиалы", reminderBtn:"Отправить напоминание", reminderTitle:"Отправить напоминание", reminderScope:"Охват: {f} (филиал можно изменить в списке выше). Сотрудники получат уведомление.", reminderTest:"Не прошедшим тест", reminderDoc:"Не ознакомившимся с документами", reminderSend:"Отправить", reminderPickType:"Выберите хотя бы один тип", reminderConfirm:"Отправить напоминание выбранным сотрудникам?", reminderSent:"Напоминание отправлено: {n} сотрудникам", testSettingsTitle:"Сроки", testDeadlineLabel:"Крайний срок прохождения теста", testDeadlineHint:"Если оставить пустым — срок не установлен. Срок показывается сотрудникам на странице теста и в напоминаниях.", certValidityLabel:"Срок действия сертификата (мес.)", certValidityHint:"Например 12 — ежегодная переаттестация: сотрудник с истёкшим сертификатом проходит тест заново (ещё 1 попытка). 0 — бессрочно.", deadlineLeft:"Срок сдачи: {d} (осталось дней: {n})", deadlinePassed:"Срок сдачи: {d} — истёк", validityInfo:"Сертификат действует {n} мес.", renewalBadge:"Переаттестация", renewalHint:"Срок сертификата истёк — сотрудник должен пройти тест заново", validUntilLbl:"действует до {d}", certColValid:"Действует до", noFilial:"Филиал не указан", totalRow:"Итого", filialSummaryBtn:"В разрезе филиалов", colBajarilish:"Выполнение (сдали)", failFilterOff:"Показать список ›", failFilterOn:"✓ Показаны только они", retakeGrantBtn:"Разрешить пересдачу", retakePendingBadge:"Разрешено", retakePendingHint:"Пересдача разрешена — сотрудник ещё не пересдал тест", retakeConfirm:"Разрешить сотруднику {fish} пересдать тест? Сотруднику будет отправлено уведомление.", denied:"У вас нет доступа к этой странице",
    notifTitle:"Уведомления", notifEmpty:"Пока нет уведомлений",
    testFormAdd:"Добавить новый вопрос", testFormEdit:"Редактировать вопрос", testListTitle:"Существующие вопросы",
    langUzTitle:"Узбекский", langRuTitle:"Русский",
    qSavol:"Вопрос", qVariantA:"Вариант A", qVariantB:"Вариант B", qVariantC:"Вариант C", qVariantD:"Вариант D",
    qCorrect:"Правильный ответ", qCorrectPh:"Выберите вариант",
    qSavolRu:"Вопрос", qVariantARu:"Ответ А", qVariantBRu:"Ответ Б", qVariantCRu:"Ответ В", qVariantDRu:"Ответ Г", qCorrectRu:"Правильный ответ",
    docFormAdd:"Добавить новый документ", docFormEdit:"Редактировать документ", docListTitle:"Существующие документы",
    docNameUz:"Название документа (узб.)", docNameRu:"Название документа (рус.)",
    docFileLabel:"PDF файл", docFileChoose:"Выбрать PDF файл", docFileHint:"Только PDF, максимум 25MB",
    docFileKeepCurrent:"Если файл не выбран, текущий файл останется без изменений",
    docFromFolderBadge:"Из папки Hujjatlar", docFromFolderHint:"Документ автоматически берётся из папки Hujjatlar — здесь можно изменить только название; чтобы заменить или удалить файл, сделайте это в самой папке",
    docFileTooLargeErr:"Размер файла слишком большой (максимум 25MB)",
    docFileTypeErr:"Неверный формат файла, принимается только PDF",
    docFileRequiredErr:"Необходимо выбрать PDF файл",
    confirmDeleteTest:"Удалить этот вопрос?", confirmDeleteDoc:"Удалить этот документ?",
    deletedOk:"Удалено ✓", editBtn:"Редактировать", deleteBtn:"Удалить",
    hubSurvey:"Анонимный опрос", hubSurveyDesc:"Вопросы опроса, результаты и статус активности",
    hubSurveyQuestions:"Вопросы опроса", hubSurveyQuestionsDesc:"Добавление, редактирование, удаление вопросов",
    hubSurveyResults:"Результаты опроса", hubSurveyResultsDesc:"Анонимная общая статистика",
    surveyFormAdd:"Добавить новый вопрос", surveyFormEdit:"Редактировать вопрос", surveyListTitle:"Существующие вопросы",
    confirmDeleteSurvey:"Удалить этот вопрос?",
    testActiveLabel:"Статус теста", surveyActiveLabel:"Статус опроса", activeOn:"Активен — сотрудники видят", activeOff:"Не активен — показывается 'завершён'",
    resultsEmpty:"Пока нет ответов", totalResponsesLbl:"ответ(ов)",
    qTuri:"Тип вопроса", qTuriTanlov:"Выбор (A, B, C, D)", qTuriYulduz:"Оценка звёздами", qTuriMatn:"Свободный ответ (текст)", qStars:"Количество звёзд",
    totalSubmissionsLbl:"Всего участников", totalQuestionsLbl:"Количество вопросов", byQuestionLbl:"Распределение ответов", avgScoreLbl:"Средний балл",
    empFormAdd:"Добавить нового сотрудника", empLoginLabel:"Логин", empPassLabel:"Пароль",
    empFamiliyaLabel:"Фамилия", empIsmLabel:"Имя", empOtasiLabel:"Отчество",
    empTugilganSanaLabel:"Дата рождения", thTugilganSana:"Дата рождения",
    empLavozimLabel:"Должность (узб.)", empLavozimRuLabel:"Должность (рус.)",
    empBolinmaLabel:"Подразделение (узб.)", empBolinmaRuLabel:"Подразделение (рус.)",
    empFilialLabel:"Филиал", thFilial:"Филиал", filialNoneOpt:"— Не выбрано —",
    filial_ijroiya_apparati:"Исполнительный аппарат",
    filial_markaziy:"Центральный филиал",
    filial_shimoliy:"Северный филиал",
    filial_sharqiy:"Восточный филиал",
    filial_janubiy:"Южный филиал",
    filial_garbiy:"Западный филиал",
    filial_janubi_garbiy:"Юго-Западный филиал",
    filial_texnik:"Специализированный технический филиал",
    filial_tms_hub:"филиал «TMS Hub»",
    hubAddEmployee:"Добавить сотрудника", hubAddEmployeeDesc:"Добавить нового сотрудника или администратора",
    empFormEdit:"Редактировать сотрудника", empPassEditHint:"Если оставить пустым, пароль не изменится",
    confirmDeleteEmployee:"Удалить этого сотрудника навсегда? Вся его история (тест, документы, уведомления) также будет удалена.",
    cannotDeleteSelfErr:"Вы не можете удалить себя", lastGlAdminErr:"В системе должен остаться хотя бы один главный администратор",
    role_user:"Сотрудник", role_anticor_admin:"Антикор — Админ", role_anticor:"Антикор",
    role_super_admin:"Супер-админ",
    empSavedOk:"Сотрудник успешно добавлен ✓", empUpdatedOk:"Данные сотрудника сохранены ✓", loginTakenErr:"Этот логин занят, пожалуйста, введите другой",
    weakPasswordErr:"Пароль должен содержать не менее 8 символов, включая заглавную и строчную буквы, цифру и спецсимвол",
    pwnedPasswordErr:"Этот пароль ранее встречался в утечках данных. Пожалуйста, выберите другой пароль.",
    passwordPolicyHint:"Минимум 8 символов: заглавная, строчная буква, цифра и спецсимвол (например !@#$%)",
    lockedBadge:"Заблокирован", lockedUntilLabel:"Блокировка до", unlockBtn:"Разблокировать",
    hubBackup:"Резервная копия", hubBackupDesc:"Создание и скачивание резервной копии базы и фото",
    backupDesc:"Полная копия базы MySQL и фотографий профилей. Для ежедневного автоматического резервного копирования настройте backend/scripts/backup.php через Планировщик задач/cron (backend/README.md).",
    backupCreateBtn:"Создать резервную копию сейчас", backupCreating:"Создание копии...",
    backupColName:"Дата / время", backupColSize:"Размер", backupDownloadBtn:"Скачать",
    hubErrorLog:"Системный журнал", hubErrorLogDesc:"Действия администраторов и ошибки сервера",
    auditTab:"Журнал действий", errorsTab:"Ошибки сервера", auditDesc:"Действия администраторов: добавление/изменение/удаление сотрудников, удаление результатов, сертификаты, настройки и т.д. (последние 2000).", auditSearchPh:"Поиск по исполнителю, действию или деталям...", auditColWho:"Кто", auditColDetails:"Подробности",
    hubCert:"Сертификат", hubCertDesc:"Подписанты сертификата и образец",
    repCertTitle:"Сертификаты", repCertDesc:"Сотрудники, получившие сертификат — просмотр и аннулирование (удаление)", colCertNumber:"Номер сертификата", colCertPdf:"PDF",
    repCertNote:"Удалённый сертификат аннулируется: сотрудник его не видит, QR-код показывает «недействителен». Результат теста сохраняется. Сертификат будет выдан снова, только если сотрудник заново успешно сдаст тест (для этого удалите его попытки в «Результатах тестов»).", certSettingsTitle:"Настройки сертификата",
    certSettingsDesc:"В блоке подписей сертификата указываются руководитель филиала сотрудника и директор департамента комплаенс. QR-код ведёт на страницу проверки подлинности сертификата. После сохранения сертификаты сотрудников обновятся автоматически при следующем открытии.",
    certColTitle:"Должность (в сертификате)", certColName:"Ф.И.О. (например: Aliyev A.A.)", certColPreview:"Образец", certKomplaensRow:"Департамент комплаенс", certPreviewLink:"Открыть",
    errorLogDesc:"Последние 200 ошибок сервера (сначала новые). Записи старше 90 дней удаляются автоматически.",
    errorLogColAction:"Действие", errorLogColMessage:"Сообщение" }
};
function applyLang(){
  document.querySelectorAll('[data-i18n]').forEach(el=>{ const k=el.getAttribute('data-i18n'); if(dict[currentLang][k]) el.textContent=dict[currentLang][k]; });
  document.getElementById('lang-uz').classList.toggle('active', currentLang==='uz');
  document.getElementById('lang-ru').classList.toggle('active', currentLang==='ru');
  document.getElementById('langPickerCode').textContent = currentLang.toUpperCase();
}
function setLang(l){ currentLang=l; localStorage.setItem('km_lang', l); applyLang(); renderAll(); if(auditEntries.length) renderAuditLog(); document.getElementById('langMenu').classList.remove('show'); }
function toggleLangMenu(){ document.getElementById('langMenu').classList.toggle('show'); }
document.addEventListener('click', (e)=>{
  if(!e.target.closest('.lang-picker')){ document.getElementById('langMenu').classList.remove('show'); }
});
applyLang();

const sunIcon = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4.5"/><path d="M12 2v2.5M12 19.5V22M4.2 4.2l1.8 1.8M18 18l1.8 1.8M2 12h2.5M19.5 12H22M4.2 19.8l1.8-1.8M18 6l1.8-1.8"/></svg>`;
const moonIcon = `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.5 14.5A8.5 8.5 0 1 1 9.5 3.5a7 7 0 1 0 11 11Z"/></svg>`;
// VAQTINCHALIK: kunduzgi rejim majburiy qilingan (tugma yashirilgan).
// Qayta yoqish uchun quyidagi qatorni qaytaring:
// let theme = localStorage.getItem('km_theme') || 'dark';
let theme = 'light';
document.querySelectorAll('.tt-sun').forEach(function(el){ el.innerHTML = sunIcon; });
document.querySelectorAll('.tt-moon').forEach(function(el){ el.innerHTML = moonIcon; });
function applyTheme(){
  document.body.setAttribute('data-theme', theme);
  document.querySelectorAll('.theme-toggle').forEach(function(btn){ btn.classList.toggle('active', theme === 'dark'); });
  document.querySelectorAll('.brand-logo').forEach(function(im){ im.src = theme==='dark' ? im.dataset.dark : im.dataset.light; });
}
function toggleTheme(){ theme = theme==='dark'?'light':'dark'; localStorage.setItem('km_theme', theme); applyTheme(); }
applyTheme();

function getCurrentUser(){
  try{ return JSON.parse(sessionStorage.getItem('km_user')); }catch(e){ return null; }
}

let statsData = null;
let filteredEmployees = [];
let usersData = null;
let filteredUsers = [];
let currentUserForKpi = null;
let testsData = null;
let editingTestId = null;
let docsData = null;
let editingDocId = null;
let surveyData = null;
let editingSurveyId = null;
let editingEmployeeId = null;
let surveyResultsData = null;

function openSection(id){
  document.querySelectorAll('#mainContent > div').forEach(d => d.style.display = 'none');
  document.getElementById(id).style.display = 'block';
}

// "O'ta olmaganlar" kartochkasi bosilganda jadval faqat ularni ko'rsatadi (qayta bosilsa — hammasi).
let statsFailedOnly = false;
function toggleFailedFilter(){
  statsFailedOnly = !statsFailedOnly;
  renderCards();
  onSearch();
}

function onSearch(){
  const q = document.getElementById('searchInput').value.trim().toLowerCase();
  filteredEmployees = statsBase().filter(e =>
    (!q || e.fish.toLowerCase().includes(q)) && (!statsFailedOnly || (e.testTaken && !e.passed)));
  renderTable();
}

/* O'ta olmagan xodimga testni qayta topshirishga ruxsat (+1 urinish, xodimga xabarnoma boradi). */
async function grantRetake(userId){
  const emp = statsData.employees.find(e => e.id === userId);
  if(!emp || !confirm(dict[currentLang].retakeConfirm.replace('{fish}', emp.fish))) return;
  try{
    const data = await apiCall({ action:'grantTestRetake', token: currentUserForKpi.token, userId });
    if(!data.success){ alert(dict[currentLang].savedErr + (data.message ? ': ' + data.message : '')); return; }
    await loadStats(currentUserForKpi);
  } catch(ex){ alert('Xatolik: ' + ex.message); }
}

// Filial filtri: '' — barcha filiallar, '__none' — filiali ko'rsatilmaganlar, aks holda filial kaliti.
let statsFilial = '';
function statsBase(){
  return statsData.employees.filter(e => !statsFilial || (statsFilial === '__none' ? !e.filial : e.filial === statsFilial));
}
function summarizeEmployees(list){
  const s = { total: list.length, docsDone: 0, testsPassed: 0, testsFailed: 0, notStarted: 0 };
  list.forEach(e => {
    if(e.hujjatSana) s.docsDone++;
    if(!e.testTaken) s.notStarted++; else if(e.passed) s.testsPassed++; else s.testsFailed++;
  });
  return s;
}
function populateStatsFilialSelect(){
  const sel = document.getElementById('statsFilial');
  const opts = [['', dict[currentLang].allFilials], ...FILIALS.map(f => [f, filialLabel(f)]), ['__none', dict[currentLang].noFilial]];
  sel.innerHTML = opts.map(([v, l]) => `<option value="${v}">${escapeHtml(l)}</option>`).join('');
  sel.value = statsFilial;
}
function setStatsFilial(v){
  statsFilial = v;
  document.getElementById('statsFilial').value = v;
  renderCards();
  renderFilialSummary();
  onSearch();
  if(document.getElementById('reminderPanel').style.display !== 'none') previewReminders();
}

/* ---------- Eslatma yuborish ---------- */
function toggleReminderPanel(){
  const el = document.getElementById('reminderPanel');
  el.style.display = el.style.display === 'none' ? 'block' : 'none';
  document.getElementById('remMsg').className = 'msg';
  if(el.style.display !== 'none') previewReminders();
}
function reminderTypes(){
  return [document.getElementById('remTest').checked ? 'test' : null, document.getElementById('remDoc').checked ? 'doc' : null].filter(Boolean);
}
async function previewReminders(){
  const scope = statsFilial === '' ? dict[currentLang].allFilials : (statsFilial === '__none' ? dict[currentLang].noFilial : filialLabel(statsFilial));
  document.getElementById('reminderScope').textContent = dict[currentLang].reminderScope.replace('{f}', scope);
  try{
    const data = await apiCall({ action:'sendReminders', token: currentUserForKpi.token, types:['test','doc'], filial: statsFilial, preview: true });
    if(!data.success) return;
    document.getElementById('remTestCount').textContent = `(${data.counts.test})`;
    document.getElementById('remDocCount').textContent = `(${data.counts.doc})`;
  } catch(ex){}
}
async function sendReminders(){
  const msg = document.getElementById('remMsg'); msg.className = 'msg';
  const types = reminderTypes();
  if(!types.length){ msg.textContent = dict[currentLang].reminderPickType; msg.className = 'msg err'; return; }
  if(!confirm(dict[currentLang].reminderConfirm)) return;
  const btn = document.getElementById('remSendBtn'); btn.disabled = true;
  try{
    const data = await apiCall({ action:'sendReminders', token: currentUserForKpi.token, types, filial: statsFilial });
    if(data.success){
      const total = Object.values(data.counts).reduce((a, b) => a + b, 0);
      msg.textContent = dict[currentLang].reminderSent.replace('{n}', total); msg.className = 'msg ok';
    } else { msg.textContent = data.message || dict[currentLang].savedErr; msg.className = 'msg err'; }
  } catch(ex){ msg.textContent = dict[currentLang].savedErr; msg.className = 'msg err'; }
  finally { btn.disabled = false; }
}
function toggleFilialSummary(){
  const el = document.getElementById('filialSummary');
  el.style.display = el.style.display === 'none' ? 'block' : 'none';
  renderFilialSummary();
}
function renderFilialSummary(){
  const el = document.getElementById('filialSummary');
  if(!statsData || el.style.display === 'none') return;
  const pct = s => s.total ? Math.round(s.testsPassed / s.total * 100) + '%' : '—';
  const row = (key, label, s, cls) => `<tr class="${cls}${key === statsFilial && cls === 'clickable' ? ' active' : ''}"${cls === 'clickable' ? ` onclick="setStatsFilial('${key}')"` : ''}>
      <td>${escapeHtml(label)}</td><td>${s.total}</td><td>${s.docsDone}</td><td>${s.testsPassed}</td><td>${s.testsFailed}</td><td>${s.notStarted}</td><td>${pct(s)}</td></tr>`;
  const all = statsData.employees;
  const rows = FILIALS.map(f => row(f, filialLabel(f), summarizeEmployees(all.filter(e => e.filial === f)), 'clickable'));
  const none = all.filter(e => !e.filial);
  if(none.length) rows.push(row('__none', dict[currentLang].noFilial, summarizeEmployees(none), 'clickable'));
  rows.push(row('', dict[currentLang].totalRow, summarizeEmployees(all), 'clickable total'));
  document.getElementById('filialSummaryTbody').innerHTML = rows.join('');
}

function renderDeadlineInfo(){
  const el = document.getElementById('deadlineInfo');
  const sum = statsData.summary || {};
  const parts = [];
  if(sum.deadline){
    const days = Math.ceil((new Date(sum.deadline + 'T23:59:59') - new Date()) / 86400000);
    const d = formatBirthDate(sum.deadline);
    parts.push(days >= 0 ? dict[currentLang].deadlineLeft.replace('{d}', d).replace('{n}', days) : dict[currentLang].deadlinePassed.replace('{d}', d));
  }
  if(sum.validityMonths > 0) parts.push(dict[currentLang].validityInfo.replace('{n}', sum.validityMonths));
  el.textContent = parts.join(' · ');
  el.style.display = parts.length ? 'block' : 'none';
}

function renderCards(){
  renderDeadlineInfo();
  const s = summarizeEmployees(statsBase());
  document.getElementById('cards').innerHTML = `
    <div class="stat-card"><div class="num">${s.total}</div><div class="lbl">${dict[currentLang].totalLbl}</div></div>
    <div class="stat-card teal"><div class="num">${s.docsDone}</div><div class="lbl">${dict[currentLang].docsLbl}</div></div>
    <div class="stat-card teal"><div class="num">${s.testsPassed}</div><div class="lbl">${dict[currentLang].passLbl}</div></div>
    <div class="stat-card coral clickable${statsFailedOnly ? ' active' : ''}" onclick="toggleFailedFilter()" title="${dict[currentLang].failFilterHint}"><div class="num">${s.testsFailed}</div><div class="lbl">${dict[currentLang].failLbl}</div><div class="hint">${statsFailedOnly ? dict[currentLang].failFilterOn : dict[currentLang].failFilterOff}</div></div>
    <div class="stat-card amber"><div class="num">${s.notStarted}</div><div class="lbl">${dict[currentLang].waitLbl}</div></div>
  `;
}

// Sheets'dan kelgan matnni HTML sifatida joylashtirishdan oldin xavfsizlashtiradi (XSS himoyasi)
function escapeHtml(s){
  return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

/* Backend "YYYY-MM-DD" (yoki bo'sh/null) qaytaradi — ko'rsatish uchun kun.oy.yil qilib beradi. */
function formatBirthDate(v){
  if(!v) return '';
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(v);
  return m ? `${m[3]}.${m[2]}.${m[1]}` : v;
}

/* ---------- Hisobotlarni haqiqiy Excel (.xlsx) fayl sifatida yuklab olish ---------- */
// Lokal assets/xlsx-lite.js orqali (tashqi CDN'siz — ichki tarmoqda ham ishlaydi):
// CSV'dagi vergul/nuqta-vergul ajratkich muammosisiz, ustunlar Excel'da har doim
// to'g'ri ajralib chiqadi (mamlakat/til sozlamalaridan qat'i nazar).
function downloadXlsx(filename, rows, sheetName){
  downloadXlsxMulti(filename, [{ name: sheetName || 'Hisobot', rows }]);
}
// Bir nechta varaqli (masalan "bajargan / bajarmagan" ro'yxatlari alohida
// list qilib) Excel fayl yaratadi. sheets: [{name, rows}, ...]
function downloadXlsxMulti(filename, sheets){
  if(typeof XlsxLite === 'undefined'){
    alert(dict[currentLang].xlsxLibErr);
    return;
  }
  XlsxLite.download(filename, sheets);
}
function todayStamp(){
  const d = new Date();
  return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
}

// Har bir hisobot o'zi kerakli ma'lumotni serverdan (qayta) so'raydi — shu sabab
// "Hisobotlar" bo'limiga boshqa sahifalarga kirmasdan to'g'ridan-to'g'ri kirilsa ham ishlaydi.

async function exportUsersReport(){
  try{
    const data = await apiCall({ action:'getUsersReport', token: currentUserForKpi.token });
    if(!data.success) return alert(dict[currentLang].savedErr);
    const rows = [['ID','Login','Parol','Familiya','Ism',"Otasining ismi","Tug'ilgan sana",'Lavozim',"Bo'linma",'Filial','Telefon','Lavozim RU',"Bo'linma RU",'Rol',"Hujjatlar bilan tanishgan","Test topshirgan"]];
    data.users.forEach(u => {
      rows.push([
        u.id, u.login, '', u.familiya, u.ism, u.otasi || '', formatBirthDate(u.tugilganSana), u.lavozim || '', u.bolinma || '', filialLabel(u.filial),
        u.telefon || '', u.lavozimRu || '', u.bolinmaRu || '', u.rol,
        u.hujjatTanishgan ? 'Ha' : "Yo'q", u.testTopshirgan ? 'Ha' : "Yo'q"
      ]);
    });
    downloadXlsx(`users_${todayStamp()}.xlsx`, rows, 'Users');
  } catch(ex){ alert('Xatolik: ' + ex.message); }
}

async function exportProgressReport(){
  try{
    const data = await apiCall({ action:'getProgressReport', token: currentUserForKpi.token });
    if(!data.success) return alert(dict[currentLang].savedErr);
    const rows = [['ID','F.I.Sh','Telefon',"Tanishgan sana","Qayta tanishgan",'Test','Natija',"Qayta topshirishga ruxsat","Qayta topshirish"]];
    data.rows.forEach(r => {
      rows.push([r.id, r.fish, r.telefon || '', r.tanishganSana, r.qaytaTanishgan, r.test, r.natija, r.qaytaRuxsat || '', r.qaytaTopshirish]);
    });
    downloadXlsx(`progress_${todayStamp()}.xlsx`, rows, 'Progress');
  } catch(ex){ alert('Xatolik: ' + ex.message); }
}

// Korrupsiyaga qarshi kurashish sahifasida allaqachon yuklangan statsData'dan
// foydalanib, hujjat/test bo'yicha bajargan va bajarmaganlarni solishtiradi —
// alohida so'rov yubormasdan, ekranda ko'rinayotgan aynan shu ma'lumot asosida.
function exportComplianceReport(){
  if(!statsData || !statsData.employees || !statsData.employees.length){
    alert(dict[currentLang].savedErr);
    return;
  }
  const header = ['F.I.Sh','Lavozim',"Bo'linma",'Filial','Telefon'];
  const bothHeader = [...header, 'Hujjat sanasi', 'Test natijasi'];
  const toRow = e => [e.fish, e.lavozim || '', e.bolinma || '', e.filial ? filialLabel(e.filial) : '', e.telefon || ''];

  // Filial tanlangan bo'lsa — faqat shu filial bo'yicha.
  const base = statsBase();
  const bothDone = base.filter(e => e.hujjatSana && e.testTaken);
  const noDoc = base.filter(e => !e.hujjatSana);
  const noTest = base.filter(e => !e.testTaken);
  const bySummary = (label, list) => { const s = summarizeEmployees(list); return [label, s.total, s.docsDone, s.testsPassed, s.testsFailed, s.notStarted, s.total ? Math.round(s.testsPassed / s.total * 100) : 0]; };
  const filialRows = [["Filial", "Jami xodimlar", "Hujjat bilan tanishgan", "Testdan o'tgan", "O'tmagan", "Boshlamagan", "Bajarilish, %"],
    ...FILIALS.map(f => bySummary(filialLabel(f), statsData.employees.filter(e => e.filial === f))),
    bySummary(dict[currentLang].noFilial, statsData.employees.filter(e => !e.filial)),
    bySummary(dict[currentLang].totalRow, statsData.employees)];

  const sheets = [
    { name: dict[currentLang].complianceBothSheet, rows: [bothHeader, ...bothDone.map(e => [
      ...toRow(e), e.hujjatSana, e.testTaken ? `${e.testPoints} — ${e.testPercent}%${e.passed ? '' : ' ('+dict[currentLang].colOtmadi+')'}` : ''
    ])] },
    { name: dict[currentLang].complianceNoDocSheet, rows: [header, ...noDoc.map(toRow)] },
    { name: dict[currentLang].complianceNoTestSheet, rows: [header, ...noTest.map(toRow)] },
    { name: dict[currentLang].filialSummaryBtn, rows: filialRows },
  ];
  downloadXlsxMulti(`muvofiqlik_${todayStamp()}.xlsx`, sheets);
}

async function exportSupportReport(){
  try{
    const data = await apiCall({ action:'getSupportRequests', token: currentUserForKpi.token });
    if(!data.success) return alert(dict[currentLang].savedErr);
    const rows = [['ID','Login','F.I.Sh',"Telefon raqam",'Murojaat','Izoh',"Ko'rib chiqdi"]];
    data.requests.forEach(r => {
      const comments = r.comments || [];
      const izohCol = comments.map(c => c.izoh).join('\n');
      const koribChiqdiCol = comments.map(c => c.fish).join('\n');
      rows.push([r.id, r.login, r.fish, r.telefon || '', r.murojaat, izohCol, koribChiqdiCol]);
    });
    downloadXlsx(`support_${todayStamp()}.xlsx`, rows, 'Support');
  } catch(ex){ alert('Xatolik: ' + ex.message); }
}

async function exportTestQuestionsXlsx(){
  try{
    const data = await apiCall({ action:'getTestQuestions', token: currentUserForKpi.token });
    if(!data.success) return alert(dict[currentLang].savedErr);
    const rows = [['ID','Savol','Variant A','Variant B','Variant C','Variant D',"To'g'ri javob",'Вопрос','Ответ А','Ответ Б','Ответ В','Ответ Г','Правильный ответ']];
    data.questions.forEach((q, i) => {
      rows.push([
        i + 1, q.uz.savol, q.uz.a || '', q.uz.b || '', q.uz.c || '', q.uz.d || '', q.uz.correct || '',
        q.ru.savol || '', q.ru.a || '', q.ru.b || '', q.ru.c || '', q.ru.d || '', q.ru.correct || ''
      ]);
    });
    downloadXlsx(`tests_${todayStamp()}.xlsx`, rows, 'Tests');
  } catch(ex){ alert('Xatolik: ' + ex.message); }
}

async function exportTestAttemptsReport(){
  try{
    const data = await apiCall({ action:'getTestAttemptsRaw', token: currentUserForKpi.token });
    if(!data.success) return alert(dict[currentLang].savedErr);
    const rows = [['ID','F.I.Sh','Sana','Ball','Maksimal ball','Foiz','Natija']];
    data.attempts.forEach(a => {
      rows.push([a.id, a.fish, a.sana, a.ball, a.maxBall, a.foiz, a.otdi ? dict[currentLang].colOtdi : dict[currentLang].colOtmadi]);
    });
    downloadXlsx(`test_natijalari_${todayStamp()}.xlsx`, rows, 'Test natijalari');
  } catch(ex){ alert('Xatolik: ' + ex.message); }
}

async function exportCertificatesReport(){
  try{
    const data = await apiCall({ action:'getCertificatesList', token: currentUserForKpi.token });
    if(!data.success) return alert(dict[currentLang].savedErr);
    const rows = [['Sertifikat raqami','F.I.Sh','Filial','Ball','Sana','Amal qiladi']];
    data.certificates.forEach(c => rows.push([c.raqam, c.fish, filialLabel(c.filial), c.ball, c.sana, c.amalQiladi || '']));
    downloadXlsx(`sertifikatlar_${todayStamp()}.xlsx`, rows, 'Sertifikatlar');
  } catch(ex){ alert('Xatolik: ' + ex.message); }
}

async function exportDocReadsReport(){
  try{
    const data = await apiCall({ action:'getDocReadsRaw', token: currentUserForKpi.token });
    if(!data.success) return alert(dict[currentLang].savedErr);
    const rows = [['ID','F.I.Sh','Sana']];
    data.reads.forEach(r => {
      rows.push([r.id, r.fish, r.sana]);
    });
    downloadXlsx(`hujjat_tanishish_${todayStamp()}.xlsx`, rows, 'Hujjat tanishish');
  } catch(ex){ alert('Xatolik: ' + ex.message); }
}

async function exportNotificationsReport(){
  try{
    const data = await apiCall({ action:'getNotificationsRaw', token: currentUserForKpi.token });
    if(!data.success) return alert(dict[currentLang].savedErr);
    const rows = [['ID',"Yuboruvchi login","Yuboruvchi F.I.Sh",'Sana','Matn',"Qamrov turi","Qamrov qiymati"]];
    data.notifications.forEach(n => {
      rows.push([n.id, n.senderLogin, n.senderFish, n.sana, n.text, n.targetType, n.targetValue]);
    });
    downloadXlsx(`xabarnoma_${todayStamp()}.xlsx`, rows, 'Xabarnoma');
  } catch(ex){ alert('Xatolik: ' + ex.message); }
}

async function exportNotificationReadsReport(){
  try{
    const data = await apiCall({ action:'getNotificationReadsRaw', token: currentUserForKpi.token });
    if(!data.success) return alert(dict[currentLang].savedErr);
    const rows = [['ID',"Xabarnoma ID",'Login','F.I.Sh',"O'qilgan sana"]];
    data.reads.forEach(r => {
      rows.push([r.id, r.notificationId, r.login, r.fish, r.sana]);
    });
    downloadXlsx(`xabarnoma_oqildi_${todayStamp()}.xlsx`, rows, "Xabarnoma o'qildi");
  } catch(ex){ alert('Xatolik: ' + ex.message); }
}

async function exportSurveyQuestionsReport(){
  try{
    const data = await apiCall({ action:'getSurveyQuestions', token: currentUserForKpi.token });
    if(!data.success) return alert(dict[currentLang].savedErr);
    const rows = [['ID','Savol','Variant A','Variant B','Variant C','Variant D','Turi',"Yulduzchalar soni",'Вопрос','Ответ А','Ответ Б','Ответ В','Ответ Г']];
    data.questions.forEach((q, i) => {
      rows.push([i + 1, q.uz.savol, q.uz.a || '', q.uz.b || '', q.uz.c || '', q.uz.d || '', q.turi, q.stars, q.ru.savol || '', q.ru.a || '', q.ru.b || '', q.ru.c || '', q.ru.d || '']);
    });
    downloadXlsx(`anonim_sorovnoma_${todayStamp()}.xlsx`, rows, "So'rovnoma");
  } catch(ex){ alert('Xatolik: ' + ex.message); }
}

async function exportSurveyAnswersWide(){
  try{
    const data = await apiCall({ action:'getSurveyAnswersWide', token: currentUserForKpi.token });
    if(!data.success) return alert(dict[currentLang].savedErr);
    const header = ['ID','Sana', ...(data.questions || [])];
    const rows = [header];
    data.submissions.forEach(s => {
      rows.push([s.id, s.sana, ...s.answers]);
    });
    downloadXlsx(`anonim_sorovnoma_javoblari_${todayStamp()}.xlsx`, rows, 'Javoblar');
  } catch(ex){ alert('Xatolik: ' + ex.message); }
}

/* ---------- Statistik yozuvlarni ko'rish va tanlab o'chirish (Hisobotlar) ----------
   Sinov/namoyish paytida yozilib qolgan test natijalari, hujjat tanishish
   belgilari, so'rovnoma javoblari va xabarnomalarni admin checkbox bilan
   tanlab o'chira olishi uchun umumiy (bir nechta turga xizmat qiluvchi) ko'rinish. */
const DDV_CONFIGS = {
  testAttempts: {
    titleKey: 'repTestAttemptsTitle',
    fetchAction: 'getTestAttemptsRaw',
    dataKey: 'attempts',
    deleteAction: 'deleteTestAttempts',
    columns: [
      { key:'id', labelKey:'thId' },
      { key:'fish', labelKey:'thFish' },
      { key:'sana', labelKey:'thSana' },
      { key:'ball', labelKey:'colBall', fmt:r => `${r.ball}/${r.maxBall}` },
      { key:'foiz', labelKey:'colFoiz', fmt:r => `${r.foiz}%` },
      { key:'otdi', labelKey:'colNatija', fmt:r => r.otdi ? dict[currentLang].colOtdi : dict[currentLang].colOtmadi },
    ],
  },
  certificates: {
    titleKey: 'repCertTitle',
    noteKey: 'repCertNote',
    fetchAction: 'getCertificatesList',
    dataKey: 'certificates',
    deleteAction: 'revokeCertificates',
    columns: [
      { key:'raqam', labelKey:'colCertNumber' },
      { key:'fish', labelKey:'thFish' },
      { key:'filial', labelKey:'thFilial', fmt:r => filialLabel(r.filial) },
      { key:'ball', labelKey:'colBall' },
      { key:'sana', labelKey:'thSana' },
      { key:'amalQiladi', labelKey:'certColValid', fmt:r => r.amalQiladi || '—' },
      { key:'pdf', labelKey:'colCertPdf', html:r => `<a href="${API_BASE_URL.replace('backend/public/index.php','backend/public/certificate-download.php')}?userId=${encodeURIComponent(r.id)}" target="_blank" rel="noopener noreferrer" style="color:var(--azure); font-weight:600;">${escapeHtml(dict[currentLang].certPreviewLink)}</a>` },
    ],
  },
  docReads: {
    titleKey: 'repDocReadsTitle',
    fetchAction: 'getDocReadsRaw',
    dataKey: 'reads',
    deleteAction: 'deleteDocReads',
    columns: [
      { key:'id', labelKey:'thId' },
      { key:'fish', labelKey:'thFish' },
      { key:'sana', labelKey:'thSana' },
    ],
  },
  surveySubmissions: {
    titleKey: 'repSurveyATitle',
    fetchAction: 'getSurveySubmissionsRaw',
    dataKey: 'submissions',
    deleteAction: 'deleteSurveySubmissions',
    columns: [
      { key:'id', labelKey:'thId' },
      { key:'sana', labelKey:'thSana' },
      { key:'javoblarSoni', labelKey:'colJavoblarSoni' },
    ],
  },
  notifications: {
    titleKey: 'repNotifTitle',
    fetchAction: 'getNotificationsRaw',
    dataKey: 'notifications',
    deleteAction: 'deleteNotifications',
    columns: [
      { key:'id', labelKey:'thId' },
      { key:'text', labelKey:'colMatn', fmt:r => (r.text||'').length > 80 ? r.text.slice(0,80)+'…' : r.text },
      { key:'senderFish', labelKey:'colYuboruvchi' },
      { key:'sana', labelKey:'thSana' },
    ],
  },
  notificationReads: {
    titleKey: 'repNotifReadsTitle',
    fetchAction: 'getNotificationReadsRaw',
    dataKey: 'reads',
    deleteAction: 'deleteNotificationReads',
    columns: [
      { key:'id', labelKey:'thId' },
      { key:'notificationId', labelKey:'colXabarId' },
      { key:'fish', labelKey:'thFish' },
      { key:'sana', labelKey:'colOqilganSana' },
    ],
  },
};

let ddvType = null;
let ddvData = [];
let ddvSelected = new Set();

async function openDataDeleteView(type){
  ddvType = type;
  ddvSelected = new Set();
  document.getElementById('ddvTitle').textContent = dict[currentLang][DDV_CONFIGS[type].titleKey];
  const note = document.getElementById('ddvNote');
  const noteKey = DDV_CONFIGS[type].noteKey;
  note.textContent = noteKey ? dict[currentLang][noteKey] : '';
  note.style.display = noteKey ? 'block' : 'none';
  openSection('dataDeleteView');
  await ddvReload();
}

async function ddvReload(){
  const cfg = DDV_CONFIGS[ddvType];
  const tbody = document.getElementById('ddvTbody');
  tbody.innerHTML = `<tr><td class="loading-row" data-i18n="loading">Yuklanmoqda...</td></tr>`;
  try{
    const data = await apiCall({ action: cfg.fetchAction, token: currentUserForKpi.token });
    if(!data.success){ tbody.innerHTML = `<tr><td class="loading-row">Xatolik</td></tr>`; return; }
    ddvData = data[cfg.dataKey] || [];
    ddvRender();
  } catch(ex){
    tbody.innerHTML = `<tr><td class="loading-row">Xatolik: ${escapeHtml(ex.message)}</td></tr>`;
  }
}

function ddvRender(){
  const cfg = DDV_CONFIGS[ddvType];
  const headRow = document.getElementById('ddvHeadRow');
  headRow.innerHTML = `<th style="width:32px;"><input type="checkbox" id="ddvSelectAll" onchange="ddvToggleAll()"></th>` +
    cfg.columns.map(c => `<th>${escapeHtml(dict[currentLang][c.labelKey] || c.labelKey)}</th>`).join('');
  const tbody = document.getElementById('ddvTbody');
  if(!ddvData.length){
    tbody.innerHTML = `<tr><td colspan="${cfg.columns.length+1}" class="loading-row">${dict[currentLang].noResults}</td></tr>`;
    ddvUpdateFooter();
    return;
  }
  tbody.innerHTML = ddvData.map(r => `
    <tr>
      <td><input type="checkbox" ${ddvSelected.has(r.id)?'checked':''} onchange="ddvToggleOne(${r.id}, this.checked)"></td>
      ${cfg.columns.map(c => `<td>${c.html ? c.html(r) : escapeHtml(String(c.fmt ? c.fmt(r) : (r[c.key] ?? '')))}</td>`).join('')}
    </tr>
  `).join('');
  ddvUpdateFooter();
}

function ddvToggleOne(id, checked){
  if(checked) ddvSelected.add(id); else ddvSelected.delete(id);
  ddvUpdateFooter();
}

function ddvToggleAll(){
  const checked = document.getElementById('ddvSelectAll').checked;
  ddvSelected = new Set(checked ? ddvData.map(r => r.id) : []);
  ddvRender();
}

function ddvUpdateFooter(){
  const countEl = document.getElementById('ddvSelectedCount');
  if(countEl) countEl.textContent = ddvSelected.size;
  const btn = document.getElementById('ddvDeleteBtn');
  if(btn) btn.disabled = ddvSelected.size === 0;
  const selectAll = document.getElementById('ddvSelectAll');
  if(selectAll) selectAll.checked = ddvData.length > 0 && ddvSelected.size === ddvData.length;
}

async function ddvDeleteSelected(){
  if(!ddvSelected.size) return;
  if(!confirm(dict[currentLang].confirmBulkDelete.replace('{n}', ddvSelected.size))) return;
  const cfg = DDV_CONFIGS[ddvType];
  try{
    const data = await apiCall({ action: cfg.deleteAction, token: currentUserForKpi.token, ids: Array.from(ddvSelected) });
    if(!data.success){ alert(dict[currentLang].savedErr + (data.message ? ': ' + data.message : '')); return; }
    ddvSelected = new Set();
    await ddvReload();
  } catch(ex){ alert('Xatolik: ' + ex.message); }
}

/* ---------- Zaxira nusxa (Backup) ---------- */
function formatBytes(bytes){
  if(!bytes) return '0 KB';
  const units = ['B','KB','MB','GB'];
  let i = 0; let n = bytes;
  while(n >= 1024 && i < units.length - 1){ n /= 1024; i++; }
  return `${n.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

async function loadBackups(){
  const tbody = document.getElementById('backupTbody');
  try{
    const data = await apiCall({ action:'listBackups', token: currentUserForKpi.token });
    if(!data.success){ tbody.innerHTML = `<tr><td colspan="3" class="loading-row">Xatolik</td></tr>`; return; }
    if(!data.backups.length){
      tbody.innerHTML = `<tr><td colspan="3" class="loading-row">${dict[currentLang].noResults}</td></tr>`;
      return;
    }
    tbody.innerHTML = data.backups.map(b => `
      <tr>
        <td>${escapeHtml(b.createdAt)}</td>
        <td>${formatBytes(b.sizeBytes)}</td>
        <td><a class="icon-btn" style="text-decoration:none;" title="${dict[currentLang].backupDownloadBtn}" href="${API_BASE_URL.replace('backend/public/index.php','backend/public/backup-download.php')}?name=${encodeURIComponent(b.name)}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg></a></td>
      </tr>
    `).join('');
  } catch(ex){
    tbody.innerHTML = `<tr><td colspan="3" class="loading-row">Xatolik: ${escapeHtml(ex.message)}</td></tr>`;
  }
}

async function createBackupNow(){
  const btn = document.getElementById('backupCreateBtn');
  btn.disabled = true;
  const originalText = btn.innerHTML;
  btn.innerHTML = `<span>${dict[currentLang].backupCreating}</span>`;
  try{
    const data = await apiCall({ action:'createBackup', token: currentUserForKpi.token });
    if(data.success){
      await loadBackups();
    } else {
      alert(dict[currentLang].savedErr + (data.message ? ('\n' + data.message) : ''));
    }
  } catch(ex){ alert('Xatolik: ' + ex.message); }
  btn.disabled = false;
  btn.innerHTML = originalText;
}

/* ---------- Admin amallari jurnali ---------- */
const AUDIT_LABELS = {
  uz: {
    employee_add:"Xodim qo'shildi", employee_edit:"Xodim tahrirlandi", employee_delete:"Xodim o'chirildi", employee_unlock:"Xodim blokdan chiqarildi",
    test_active:"Test holati o'zgartirildi", test_question_add:"Test savoli qo'shildi", test_question_edit:"Test savoli tahrirlandi", test_question_delete:"Test savoli o'chirildi",
    test_settings:"Test sozlamalari o'zgartirildi", retake_grant:"Qayta topshirishga ruxsat berildi",
    survey_active:"So'rovnoma holati o'zgartirildi", survey_question_add:"So'rovnoma savoli qo'shildi", survey_question_edit:"So'rovnoma savoli tahrirlandi", survey_question_delete:"So'rovnoma savoli o'chirildi",
    doc_add:"Hujjat qo'shildi", doc_edit:"Hujjat tahrirlandi", doc_delete:"Hujjat o'chirildi", hr_doc_delete:"Yuborilgan hujjat o'chirildi",
    cert_settings:"Sertifikat imzo qo'yuvchilari o'zgartirildi", cert_revoke:"Sertifikat bekor qilindi",
    delete_test_attempts:"Test natijalari o'chirildi", delete_doc_reads:"Hujjat tanishish yozuvlari o'chirildi", delete_survey_submissions:"So'rovnoma javoblari o'chirildi",
    delete_notifications:"Xabarnomalar o'chirildi", delete_notification_reads:"O'qilganlik yozuvlari o'chirildi", delete_support_requests:"Yordam so'rovlari o'chirildi",
    notification_send:"Xabarnoma yuborildi", reminders_send:"Eslatma yuborildi", backup_create:"Zaxira nusxa olindi",
    declaration_delete:"Deklaratsiya o'chirildi", purchase_add:"Xarid reyestrga kiritildi",
  },
  ru: {
    employee_add:"Добавлен сотрудник", employee_edit:"Изменён сотрудник", employee_delete:"Удалён сотрудник", employee_unlock:"Сотрудник разблокирован",
    test_active:"Изменён статус теста", test_question_add:"Добавлен вопрос теста", test_question_edit:"Изменён вопрос теста", test_question_delete:"Удалён вопрос теста",
    test_settings:"Изменены настройки теста", retake_grant:"Разрешена пересдача",
    survey_active:"Изменён статус опроса", survey_question_add:"Добавлен вопрос опроса", survey_question_edit:"Изменён вопрос опроса", survey_question_delete:"Удалён вопрос опроса",
    doc_add:"Добавлен документ", doc_edit:"Изменён документ", doc_delete:"Удалён документ", hr_doc_delete:"Удалён отправленный документ",
    cert_settings:"Изменены подписанты сертификата", cert_revoke:"Сертификат аннулирован",
    delete_test_attempts:"Удалены результаты теста", delete_doc_reads:"Удалены записи ознакомления", delete_survey_submissions:"Удалены ответы опроса",
    delete_notifications:"Удалены уведомления", delete_notification_reads:"Удалены записи о прочтении", delete_support_requests:"Удалены обращения",
    notification_send:"Отправлено уведомление", reminders_send:"Отправлено напоминание", backup_create:"Создана резервная копия",
    declaration_delete:"Удалена декларация", purchase_add:"Закупка внесена в реестр",
  },
};
const auditLabel = code => (AUDIT_LABELS[currentLang] && AUDIT_LABELS[currentLang][code]) || code;
let auditEntries = [];

function showLogTab(tab){
  document.getElementById('logTabAudit').classList.toggle('active', tab === 'audit');
  document.getElementById('logTabErrors').classList.toggle('active', tab === 'errors');
  document.getElementById('auditPane').style.display = tab === 'audit' ? 'block' : 'none';
  document.getElementById('errorsPane').style.display = tab === 'errors' ? 'block' : 'none';
  if(tab === 'audit') loadAuditLog(); else loadErrorLog();
}

async function loadAuditLog(){
  const tbody = document.getElementById('auditTbody');
  try{
    const data = await apiCall({ action:'getAuditLog', token: currentUserForKpi.token });
    if(!data.success){ tbody.innerHTML = `<tr><td colspan="4" class="loading-row">${escapeHtml(data.message || 'Xatolik')}</td></tr>`; return; }
    auditEntries = data.entries || [];
    renderAuditLog();
  } catch(ex){
    tbody.innerHTML = `<tr><td colspan="4" class="loading-row">Xatolik: ${escapeHtml(ex.message)}</td></tr>`;
  }
}

function filteredAuditEntries(){
  const q = (document.getElementById('auditSearch').value || '').trim().toLowerCase();
  if(!q) return auditEntries;
  return auditEntries.filter(e => [e.sana, e.kim, auditLabel(e.amal), e.tafsilot].join(' ').toLowerCase().includes(q));
}

function renderAuditLog(){
  const tbody = document.getElementById('auditTbody');
  const rows = filteredAuditEntries();
  if(!rows.length){ tbody.innerHTML = `<tr><td colspan="4" class="loading-row">${dict[currentLang].noResults}</td></tr>`; return; }
  tbody.innerHTML = rows.map(e => `
    <tr>
      <td>${escapeHtml(e.sana)}</td>
      <td style="white-space:normal;">${escapeHtml(e.kim)}</td>
      <td style="white-space:normal;">${escapeHtml(auditLabel(e.amal))}</td>
      <td style="white-space:normal;">${escapeHtml(e.tafsilot)}</td>
    </tr>
  `).join('');
}

function exportAuditLog(){
  const rows = [['Sana','Kim','Amal','Tafsilot']];
  filteredAuditEntries().forEach(e => rows.push([e.sana, e.kim, auditLabel(e.amal), e.tafsilot]));
  downloadXlsx(`amallar_jurnali_${todayStamp()}.xlsx`, rows, 'Amallar jurnali');
}

async function loadErrorLog(){
  const tbody = document.getElementById('errorLogTbody');
  try{
    const data = await apiCall({ action:'getErrorLog', token: currentUserForKpi.token });
    if(!data.success){ tbody.innerHTML = `<tr><td colspan="3" class="loading-row">Xatolik</td></tr>`; return; }
    if(!data.entries.length){
      tbody.innerHTML = `<tr><td colspan="3" class="loading-row">${dict[currentLang].noResults}</td></tr>`;
      return;
    }
    tbody.innerHTML = data.entries.map(e => `
      <tr>
        <td>${escapeHtml(e.sana)}</td>
        <td>${escapeHtml(e.action || '—')}</td>
        <td style="white-space:normal;">${escapeHtml(e.message)}</td>
      </tr>
    `).join('');
  } catch(ex){
    tbody.innerHTML = `<tr><td colspan="3" class="loading-row">Xatolik: ${escapeHtml(ex.message)}</td></tr>`;
  }
}

// ---- Test muddatlari (topshirish muddati, sertifikat amal qilish muddati) ----
async function loadTestSettings(){
  try{
    const data = await apiCall({ action:'getTestSettings', token: currentUserForKpi.token });
    if(!data.success) return;
    document.getElementById('tsDeadline').value = data.deadline || '';
    document.getElementById('tsValidity').value = data.validityMonths;
  } catch(ex){}
}

async function saveTestSettings(e){
  e.preventDefault();
  const msg = document.getElementById('tsMsg'); msg.className = 'msg';
  const btn = document.getElementById('tsSaveBtn'); btn.disabled = true;
  try{
    const data = await apiCall({ action:'saveTestSettings', token: currentUserForKpi.token,
      deadline: document.getElementById('tsDeadline').value,
      validityMonths: document.getElementById('tsValidity').value });
    if(data.success){ msg.textContent = dict[currentLang].savedOk; msg.className = 'msg ok'; }
    else { msg.textContent = data.message || dict[currentLang].savedErr; msg.className = 'msg err'; }
  } catch(ex){ msg.textContent = dict[currentLang].savedErr; msg.className = 'msg err'; }
  finally { btn.disabled = false; }
  return false;
}

// ---- Sertifikat sozlamalari (imzo qo'yuvchilar) ----
function certPreviewUrl(filial){
  return API_BASE_URL.replace('backend/public/index.php','backend/public/certificate-preview.php') + (filial ? '?filial=' + encodeURIComponent(filial) : '');
}

async function loadCertSettings(){
  const tbody = document.getElementById('certSettingsTbody');
  try{
    const data = await apiCall({ action:'getCertificateSettings', token: currentUserForKpi.token });
    if(!data.success){ tbody.innerHTML = `<tr><td colspan="4" class="loading-row">${escapeHtml(data.message || dict[currentLang].savedErr)}</td></tr>`; return; }
    const row = (key, label, item, filial) => `
      <tr data-cert-key="${escapeHtml(key)}">
        <td>${escapeHtml(label)}</td>
        <td><input type="text" class="cert-title" maxlength="100" value="${escapeHtml(item.title)}" style="width:100%;"></td>
        <td><input type="text" class="cert-name" maxlength="100" value="${escapeHtml(item.name)}" style="width:100%;"></td>
        <td><a href="${certPreviewUrl(filial)}" target="_blank" rel="noopener noreferrer" style="color:var(--azure); font-weight:600;">${dict[currentLang].certPreviewLink}</a></td>
      </tr>`;
    tbody.innerHTML = row('komplaens', dict[currentLang].certKomplaensRow, data.komplaens, '')
      + FILIALS.map(f => row(f, filialLabel(f), data.filials[f] || {title:'', name:''}, f)).join('');
  } catch(ex){
    tbody.innerHTML = `<tr><td colspan="4" class="loading-row">Xatolik: ${escapeHtml(ex.message)}</td></tr>`;
  }
}

async function saveCertSettings(e){
  e.preventDefault();
  const msg = document.getElementById('certFormMsg'); msg.className = 'msg';
  const btn = document.getElementById('certSaveBtn'); btn.disabled = true;
  const payload = { action:'saveCertificateSettings', token: currentUserForKpi.token, komplaens:{}, filials:{} };
  document.querySelectorAll('#certSettingsTbody tr[data-cert-key]').forEach(tr => {
    const item = { title: tr.querySelector('.cert-title').value.trim(), name: tr.querySelector('.cert-name').value.trim() };
    const key = tr.getAttribute('data-cert-key');
    if(key === 'komplaens') payload.komplaens = item; else payload.filials[key] = item;
  });
  try{
    const data = await apiCall(payload);
    if(data.success){
      msg.textContent = dict[currentLang].savedOk; msg.className = 'msg ok';
      loadCertSettings();
    } else {
      msg.textContent = data.message || dict[currentLang].savedErr; msg.className = 'msg err';
    }
  } catch(ex){
    msg.textContent = dict[currentLang].savedErr; msg.className = 'msg err';
  } finally {
    btn.disabled = false;
  }
  return false;
}

function renderTable(){
  if(filteredEmployees.length === 0){
    document.getElementById('tbody').innerHTML = `<tr><td colspan="8" class="loading-row">${dict[currentLang].noResults}</td></tr>`;
    return;
  }
  document.getElementById('tbody').innerHTML = filteredEmployees.map((emp, i) => {
    const docBadge = emp.hujjatSana ? `<span class="badge ok">${escapeHtml(emp.hujjatSana)}</span>` : `<span class="badge no">${dict[currentLang].no}</span>`;
    let testBadge = `<span class="badge wait">${dict[currentLang].notTaken}</span>`;
    // Xodim testni 1 marta topshiradi (+ admin bergan qayta topshirish ruxsatlari) — urinishlar soni ko'rsatiladi.
    const maxAttempts = emp.maxAttempts || (statsData && statsData.summary && statsData.summary.maxAttempts);
    const attemptsTxt = (maxAttempts && emp.testAttempts) ? ` · ${emp.testAttempts}/${maxAttempts}` : '';
    if(emp.testTaken) testBadge = `<span class="badge ${emp.passed?'ok':'no'}" title="${escapeHtml(dict[currentLang].testAttemptsHint)}">${emp.testPoints} — ${emp.testPercent}%${attemptsTxt}</span>`;
    if(emp.hasCertificate){
      const certUrl = `${API_BASE_URL.replace('backend/public/index.php','backend/public/certificate-download.php')}?userId=${encodeURIComponent(emp.id)}`;
      const certTitle = dict[currentLang].certDownloadBtn + (emp.certValidUntil ? ' · ' + dict[currentLang].validUntilLbl.replace('{d}', formatBirthDate(emp.certValidUntil)) : '');
      testBadge += `<a class="icon-btn" style="text-decoration:none; display:inline-flex; vertical-align:middle;" title="${escapeHtml(certTitle)}" href="${certUrl}" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><circle cx="12" cy="8" r="6"/><path d="M15.48 12.89 17 22l-5-3-5 3 1.52-9.11"/></svg></a>`;
    }
    if(!emp.testTaken && emp.renewal){
      testBadge = `<span class="badge wait" title="${escapeHtml(dict[currentLang].renewalHint)}">${dict[currentLang].renewalBadge}</span>`;
    }
    if(emp.testTaken && !emp.passed){
      if(emp.retakePending){
        testBadge += `<span class="badge wait" title="${escapeHtml(dict[currentLang].retakePendingHint)}">${dict[currentLang].retakePendingBadge}</span>`;
      } else if(currentUserForKpi && ANTICOR_MANAGE.includes(currentUserForKpi.rol)){
        testBadge += `<button type="button" class="icon-btn" style="display:inline-flex; vertical-align:middle;" title="${dict[currentLang].retakeGrantBtn}" onclick="grantRetake(${emp.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg></button>`;
      }
    }
    // Test natijasi katagida bir nechta belgi (natija, sertifikat, ruxsat) bo'lishi mumkin — qirqilmasin, sig'masa keyingi qatorga o'tsin.
    return `<tr><td>${i + 1}</td><td>${escapeHtml(emp.fish)}</td><td>${escapeHtml(emp.lavozim)||'—'}</td><td>${escapeHtml(emp.bolinma)||'—'}</td><td class="nowrap">${escapeHtml(filialLabel(emp.filial))}</td><td class="nowrap">${escapeHtml(emp.telefon)||'—'}</td><td class="nowrap">${docBadge}</td><td class="nowrap"><div style="display:flex; align-items:center; gap:6px;">${testBadge}</div></td></tr>`;
  }).join('');
}

function renderAll(){
  if(!statsData) return;
  populateStatsFilialSelect();
  renderFilialSummary();
  renderCards();
  onSearch();
  if(usersData) onEmpSearch();
}

/* ---------- Xodimlar ---------- */
function openEmployeesEntry(){
  openSection('employeesHubView');
}

/* "Ortga" bitta qadam orqaga qaytishi kerak: agar mavjud xodim
   tahrirlanayotgan bo'lsa (ro'yxatdan "tahrirlash" orqali kelingan) —
   ro'yxatga, aks holda (hub kartasidan "Yangi xodim qo'shish" orqali
   kelingan) — Xodimlar bo'limiga qaytadi. */
function backFromEmployeeForm(){
  openSection(editingEmployeeId ? 'employeesView' : 'employeesHubView');
}

/* ---------- Xaridlar reyestri ---------- */
function openPurchasesEntry(){
  openSection('purchasesHubView');
}

let purchasesData = [];
let filteredPurchases = [];
let pendingPurchaseFileDataUrl = null;
const PURCHASE_FILE_MAX_BYTES = 25 * 1024 * 1024;

async function loadPurchases(){
  const tbody = document.getElementById('purchasesTbody');
  try{
    const data = await apiCall({ action:'getPurchases', token: currentUserForKpi.token });
    if(data.success){
      purchasesData = data.purchases;
      filteredPurchases = purchasesData;
      renderPurchasesTable();
    } else {
      tbody.innerHTML = `<tr><td colspan="9" class="loading-row">${dict[currentLang].savedErr}</td></tr>`;
    }
  } catch(ex){
    tbody.innerHTML = `<tr><td colspan="9" class="loading-row">Xatolik: ${ex.message}</td></tr>`;
  }
}

function onPurchaseSearch(){
  const q = document.getElementById('purchaseSearchInput').value.trim().toLowerCase();
  filteredPurchases = !q ? purchasesData : purchasesData.filter(p =>
    (p.shartnomaRaqami||'').toLowerCase().includes(q) || (p.kontragent||'').toLowerCase().includes(q)
  );
  renderPurchasesTable();
}

function formatSumma(v){
  const n = Number(v) || 0;
  return n.toLocaleString('ru-RU', { minimumFractionDigits:2, maximumFractionDigits:2 });
}

function renderPurchasesTable(){
  const tbody = document.getElementById('purchasesTbody');
  if(!filteredPurchases || filteredPurchases.length === 0){
    tbody.innerHTML = `<tr><td colspan="9" class="loading-row">${dict[currentLang].noResults}</td></tr>`;
    return;
  }
  tbody.innerHTML = filteredPurchases.map(p => `
    <tr>
      <td>${p.id}</td>
      <td>${escapeHtml(formatBirthDate(p.shartnomaSana))}</td>
      <td>${escapeHtml(p.shartnomaRaqami)}</td>
      <td>${escapeHtml(p.kontragent)}</td>
      <td>${escapeHtml(p.shartnomaPredmeti)}</td>
      <td>${formatSumma(p.shartnomaSummasi)}</td>
      <td>${escapeHtml(p.xaridTuri)}</td>
      <td>${escapeHtml(p.izoh || '') || '—'}</td>
      <td><a class="icon-btn" style="text-decoration:none;" title="${dict[currentLang].downloadBtn}" href="${API_BASE_URL.replace('backend/public/index.php','backend/public/purchase-download.php')}?id=${encodeURIComponent(p.id)}" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg></a></td>
    </tr>
  `).join('');
}

function exportPurchasesReport(){
  if(!filteredPurchases || filteredPurchases.length === 0){
    alert(dict[currentLang].savedErr);
    return;
  }
  const rows = [['T/r','Shartnoma sanasi','Shartnoma raqami','Kontragent','Shartnoma predmeti','Shartnoma summasi','Xarid turi','Izoh']];
  filteredPurchases.forEach(p => {
    rows.push([p.id, formatBirthDate(p.shartnomaSana), p.shartnomaRaqami, p.kontragent, p.shartnomaPredmeti, p.shartnomaSummasi, p.xaridTuri, p.izoh || '']);
  });
  downloadXlsx(`xaridlar_${todayStamp()}.xlsx`, rows, 'Xaridlar');
}

async function onPurchaseFileChange(e){
  const file = e.target.files[0];
  pendingPurchaseFileDataUrl = null;
  if(!file) return;
  const msg = document.getElementById('purchaseFormMsg'); msg.className = 'msg';
  if(file.size > PURCHASE_FILE_MAX_BYTES){
    msg.textContent = dict[currentLang].docFileTooLargeErr; msg.classList.add('err');
    e.target.value = ''; document.getElementById('pFileBtnText').textContent = dict[currentLang].docFileChoose;
    return;
  }
  if(file.type !== 'application/pdf'){
    msg.textContent = dict[currentLang].docFileTypeErr; msg.classList.add('err');
    e.target.value = ''; document.getElementById('pFileBtnText').textContent = dict[currentLang].docFileChoose;
    return;
  }
  try{
    pendingPurchaseFileDataUrl = await readFileAsDataUrl(file);
    document.getElementById('pFileBtnText').textContent = file.name;
  } catch(ex){
    msg.textContent = dict[currentLang].docFileTypeErr; msg.classList.add('err');
    e.target.value = ''; document.getElementById('pFileBtnText').textContent = dict[currentLang].docFileChoose;
  }
}

function cancelPurchaseForm(){
  pendingPurchaseFileDataUrl = null;
  document.querySelector('#purchaseEntryView form').reset();
  document.getElementById('pFileBtnText').textContent = dict[currentLang].docFileChoose;
  document.getElementById('purchaseFormMsg').className = 'msg';
}

async function submitPurchaseForm(e){
  e.preventDefault();
  const msg = document.getElementById('purchaseFormMsg'); msg.className = 'msg';
  if(!pendingPurchaseFileDataUrl){
    msg.textContent = dict[currentLang].purchaseFileRequiredErr; msg.classList.add('err');
    return false;
  }
  const payload = {
    action: 'addPurchase', token: currentUserForKpi.token,
    shartnomaSana: document.getElementById('pShartnomaSana').value,
    shartnomaRaqami: document.getElementById('pShartnomaRaqami').value.trim(),
    kontragent: document.getElementById('pKontragent').value.trim(),
    shartnomaPredmeti: document.getElementById('pShartnomaPredmeti').value.trim(),
    shartnomaSummasi: document.getElementById('pShartnomaSummasi').value,
    xaridTuri: document.getElementById('pXaridTuri').value.trim(),
    izoh: document.getElementById('pIzoh').value.trim(),
    file: pendingPurchaseFileDataUrl
  };
  const btn = document.getElementById('purchaseSubmitBtn'); btn.disabled = true;
  try{
    const data = await apiCall(payload);
    btn.disabled = false;
    if(data.success){
      cancelPurchaseForm();
      msg.textContent = dict[currentLang].savedOk; msg.classList.add('ok');
    } else if(data.code === 'FILE_TOO_LARGE'){
      msg.textContent = dict[currentLang].docFileTooLargeErr; msg.classList.add('err');
    } else if(data.code === 'INVALID_FILE'){
      msg.textContent = dict[currentLang].docFileTypeErr; msg.classList.add('err');
    } else {
      msg.textContent = dict[currentLang].savedErr; msg.classList.add('err');
    }
  } catch(ex){
    btn.disabled = false;
    msg.textContent = 'Xatolik: ' + ex.message; msg.classList.add('err');
  }
  return false;
}

function onEmpSearch(){
  const q = document.getElementById('empSearchInput').value.trim().toLowerCase();
  filteredUsers = !q ? usersData : usersData.filter(u => (u.familiya+' '+u.ism+' '+(u.otasi||'')).toLowerCase().includes(q) || u.login.toLowerCase().includes(q));
  renderUsersTable();
}

function renderUsersTable(){
  if(filteredUsers.length === 0){
    document.getElementById('empTbody').innerHTML = `<tr><td colspan="7" class="loading-row">${dict[currentLang].noResults}</td></tr>`;
    return;
  }
  const canEditEmp = currentUserForKpi && HR_EDIT.includes(currentUserForKpi.rol);
  const canDeleteEmp = currentUserForKpi && HR_MANAGE.includes(currentUserForKpi.rol);
  const canUnlock = currentUserForKpi && HR_MANAGE.includes(currentUserForKpi.rol);
  // Backend (AdminController::EDITABLE_TARGET_ROLES) bilan bir xil: super-admin
  // bo'lmagan admin super-admin'dan boshqa barcha xodimlarni tahrirlay/o'chira oladi.
  const viewerIsSuper = currentUserForKpi && currentUserForKpi.rol === 'super-admin';
  document.getElementById('empTbody').innerHTML = filteredUsers.map((u, i) => {
    const fish = [u.familiya, u.ism, u.otasi].filter(Boolean).join(' ');
    const rowEditable = viewerIsSuper || ['user', 'anticor', 'anticor-admin'].includes(u.rol);
    const lockedBadge = u.locked ? `<span class="badge no" style="margin-left:6px;" title="${dict[currentLang].lockedUntilLabel}: ${escapeHtml(u.lockedUntil||'')}">${dict[currentLang].lockedBadge}</span>` : '';
    const unlockBtn = (u.locked && canUnlock) ? `<button type="button" class="icon-btn" title="${dict[currentLang].unlockBtn}" onclick="unlockEmployeeRow('${escapeHtml(u.login)}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg></button>` : '';
    const editBtn = (canEditEmp && rowEditable) ? `<button type="button" class="icon-btn" title="${dict[currentLang].editBtn}" onclick="startEditEmployee(${u.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg></button>` : '';
    const deleteBtn = (canDeleteEmp && rowEditable) ? `<button type="button" class="icon-btn danger" title="${dict[currentLang].deleteBtn}" onclick="deleteEmployeeRow(${u.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/></svg></button>` : '';
    const actions = (unlockBtn || editBtn || deleteBtn) ? `<div class="row-actions">${unlockBtn}${editBtn}${deleteBtn}</div>` : '—';
    return `<tr><td>${u.displayId ?? (i+1)}</td><td>${escapeHtml(fish)}${lockedBadge}</td><td class="nowrap">${escapeHtml(formatBirthDate(u.tugilganSana))||'—'}</td><td>${escapeHtml(u.lavozim)||'—'}</td><td>${escapeHtml(u.bolinma)||'—'}</td><td class="nowrap">${escapeHtml(filialLabel(u.filial))}</td><td class="nowrap">${escapeHtml(u.telefon)||'—'}</td><td class="nowrap">${actions}</td></tr>`;
  }).join('');
}

/**
 * Rol tanlash ro'yxatini chaqiruvchining huquq darajasiga qarab quradi:
 * super-admin barcha rollarni, jumladan super-admin'ning o'zini ham
 * (rolni topshirish/transfer uchun) beradi; anticor-admin — super-admin'dan
 * BOSHQA barcha rolni beradi (backend/src/Controllers/AdminController.php
 * allowedRolesFor() bilan bir xil mantiq) — super-admin varianti hech
 * qachon ko'rsatilmaydi.
 */
function populateRoleSelect(currentValue){
  const rol = currentUserForKpi && currentUserForKpi.rol;
  const withSuper = ['user','anticor-admin','anticor','super-admin'];
  const withoutSuper = ['user','anticor-admin','anticor'];
  const options = rol === 'super-admin' ? withSuper : withoutSuper;
  const sel = document.getElementById('eRol');
  sel.innerHTML = options.map(r => `<option value="${r}">${escapeHtml(dict[currentLang]['role_' + r.replace(/-/g,'_')] || r)}</option>`).join('');
  if(currentValue && options.includes(currentValue)) sel.value = currentValue;
  sel.disabled = false;
}

// backend/src/Filials.php bilan bir xil belgilangan ro'yxat (erkin matn emas — tanlov orqali).
const FILIALS = ['ijroiya_apparati','markaziy','shimoliy','sharqiy','janubiy','garbiy','janubi_garbiy','texnik','tms_hub'];

function filialLabel(f){
  return f ? (dict[currentLang]['filial_' + f] || f) : '—';
}

function populateFilialSelect(currentValue){
  const sel = document.getElementById('eFilial');
  sel.innerHTML = `<option value="">${escapeHtml(dict[currentLang].filialNoneOpt)}</option>` +
    FILIALS.map(f => `<option value="${f}">${escapeHtml(dict[currentLang]['filial_' + f])}</option>`).join('');
  sel.value = (currentValue && FILIALS.includes(currentValue)) ? currentValue : '';
}

function startEditEmployee(id){
  const u = usersData.find(x => String(x.id) === String(id));
  if(!u) return;
  editingEmployeeId = id;
  document.getElementById('eLogin').value = u.login || '';
  document.getElementById('eLogin').disabled = true;
  document.getElementById('eParol').required = false;
  document.getElementById('eParol').value = '';
  document.getElementById('eParolHint').style.display = 'block';
  document.getElementById('eFamiliya').value = u.familiya || '';
  document.getElementById('eIsm').value = u.ism || '';
  document.getElementById('eOtasi').value = u.otasi || '';
  document.getElementById('eTugilganSana').value = u.tugilganSana || '';
  document.getElementById('eLavozim').value = u.lavozim || '';
  document.getElementById('eLavozimRu').value = u.lavozimRu || '';
  document.getElementById('eBolinma').value = u.bolinma || '';
  document.getElementById('eBolinmaRu').value = u.bolinmaRu || '';
  populateFilialSelect(u.filial || '');
  document.getElementById('eTelefon').value = u.telefon || '';
  populateRoleSelect(u.rol || 'user');
  document.getElementById('empFormEyebrow').textContent = dict[currentLang].empFormEdit;
  document.getElementById('empFormTitle').textContent = dict[currentLang].empFormEdit;
  document.getElementById('empCancelBtn').style.display = 'inline-flex';
  document.getElementById('empFormMsg').className = 'msg';
  document.getElementById('eLoginWarning').classList.remove('show');
  openSection('addEmployeeView');
}

function cancelEmployeeEdit(){
  editingEmployeeId = null;
  document.getElementById('eLogin').disabled = false;
  document.getElementById('eParol').required = true;
  document.getElementById('eParolHint').style.display = 'none';
  document.getElementById('empFormEyebrow').textContent = dict[currentLang].hubAddEmployee;
  document.getElementById('empFormTitle').textContent = dict[currentLang].hubAddEmployee;
  document.getElementById('empCancelBtn').style.display = 'none';
  document.querySelector('#addEmployeeView form').reset();
  document.getElementById('empFormMsg').className = 'msg';
  document.getElementById('eLoginWarning').classList.remove('show');
}

async function deleteEmployeeRow(id){
  if(!confirm(dict[currentLang].confirmDeleteEmployee)) return;
  try{
    const data = await apiCall({ action:'deleteEmployee', token: currentUserForKpi.token, id });
    if(data.success){
      loadUsersList(currentUserForKpi);
    } else if(data.code === 'CANNOT_DELETE_SELF'){
      alert(dict[currentLang].cannotDeleteSelfErr);
    } else if(data.code === 'LAST_GL_ADMIN'){
      alert(dict[currentLang].lastGlAdminErr);
    } else {
      alert(dict[currentLang].savedErr + (data.message ? ': ' + data.message : ''));
    }
  } catch(ex){ alert('Xatolik: ' + ex.message); }
}

async function unlockEmployeeRow(login){
  try{
    const data = await apiCall({ action:'unlockLogin', token: currentUserForKpi.token, login });
    if(data.success){
      loadUsersList(currentUserForKpi);
    } else {
      alert(dict[currentLang].savedErr);
    }
  } catch(ex){ alert('Xatolik: ' + ex.message); }
}

async function loadUsersList(user){
  try{
    const data = await apiCall({ action:'getUsersList', token: user.token });
    if(data.success){
      // Backend id bo'yicha (yaratilgan tartibida) qaytaradi — ko'rsatiladigan "ID" ustuni
      // shu tartibdagi joriy pozitsiya (1,2,3...): xodim o'chirilsa, qolganlar avtomatik
      // siljib, bo'shliq qolmaydi. Haqiqiy baza id'si (u.id) tahrirlash/o'chirish uchun ishlatiladi.
      usersData = data.users.map((u, i) => ({ ...u, displayId: i + 1 }));
      if(document.getElementById('employeesView').style.display !== 'none') { filteredUsers = usersData; renderUsersTable(); }
    }
  } catch(ex){}
}

/* Login maydoni tagida jonli (real vaqtda) "band" ogohlantirishi — usersData
   allaqachon xotirada yuklangani uchun serverga so'rov yubormasdan tekshiradi.
   Tahrirlash rejimida (login maydoni disabled) hech qachon ko'rsatilmaydi. */
function checkLoginTaken(){
  const warning = document.getElementById('eLoginWarning');
  const input = document.getElementById('eLogin');
  if(editingEmployeeId || input.disabled){ warning.classList.remove('show'); return false; }
  const login = input.value.trim().toLowerCase();
  const taken = login !== '' && (usersData || []).some(u => (u.login || '').toLowerCase() === login);
  warning.classList.toggle('show', taken);
  return taken;
}

async function submitEmployeeForm(e){
  e.preventDefault();
  const msg = document.getElementById('empFormMsg'); msg.className = 'msg';
  if(checkLoginTaken()){ return false; }
  const payload = {
    action: editingEmployeeId ? 'editEmployee' : 'addEmployee', token: currentUserForKpi.token,
    login: document.getElementById('eLogin').value.trim(),
    parol: document.getElementById('eParol').value,
    familiya: document.getElementById('eFamiliya').value.trim(),
    ism: document.getElementById('eIsm').value.trim(),
    otasi: document.getElementById('eOtasi').value.trim(),
    tugilganSana: document.getElementById('eTugilganSana').value,
    lavozim: document.getElementById('eLavozim').value.trim(),
    lavozimRu: document.getElementById('eLavozimRu').value.trim(),
    bolinma: document.getElementById('eBolinma').value.trim(),
    bolinmaRu: document.getElementById('eBolinmaRu').value.trim(),
    filial: document.getElementById('eFilial').value,
    telefon: document.getElementById('eTelefon').value.trim(),
    rol: document.getElementById('eRol').value
  };
  if(editingEmployeeId) payload.id = editingEmployeeId;
  const btn = document.getElementById('empSubmitBtn'); btn.disabled = true;
  try{
    const data = await apiCall(payload);
    btn.disabled = false;
    if(data.success){
      const wasEdit = !!editingEmployeeId;
      cancelEmployeeEdit();
      msg.textContent = wasEdit ? dict[currentLang].empUpdatedOk : dict[currentLang].empSavedOk; msg.classList.add('ok');
      loadUsersList(currentUserForKpi);
    } else if(data.code === 'LOGIN_TAKEN'){
      document.getElementById('eLoginWarning').classList.add('show');
    } else if(data.code === 'WEAK_PASSWORD'){
      msg.textContent = dict[currentLang].weakPasswordErr; msg.classList.add('err');
    } else if(data.code === 'PWNED_PASSWORD'){
      msg.textContent = dict[currentLang].pwnedPasswordErr; msg.classList.add('err');
    } else if(data.code === 'LAST_GL_ADMIN'){
      msg.textContent = dict[currentLang].lastGlAdminErr; msg.classList.add('err');
    } else {
      msg.textContent = dict[currentLang].savedErr + (data.message ? ': ' + data.message : ''); msg.classList.add('err');
    }
  } catch(ex){ btn.disabled = false; msg.textContent = 'Xatolik: ' + ex.message; msg.classList.add('err'); }
  return false;
}

async function loadStats(user){
  try{
    const data = await apiCall({ action:'getStats', token: user.token });
    if(!data.success) return;
    statsData = data;
    renderAll();
  } catch(ex){
    document.getElementById('tbody').innerHTML = `<tr><td colspan="7" class="loading-row">Xatolik: ${ex.message}</td></tr>`;
  }
}

/* ---------- Test savollari (CRUD) ---------- */
function refreshCorrectSelects(){
  const uzOpts = [document.getElementById('tA').value, document.getElementById('tB').value, document.getElementById('tC').value, document.getElementById('tD').value];
  const ruOpts = [document.getElementById('tARu').value, document.getElementById('tBRu').value, document.getElementById('tCRu').value, document.getElementById('tDRu').value];
  const selUz = document.getElementById('tCorrect'), selRu = document.getElementById('tCorrectRu');
  const prevUz = selUz.value, prevRu = selRu.value;
  const phUz = dict[currentLang].qCorrectPh, phRu = dict[currentLang].qCorrectPh;
  selUz.innerHTML = `<option value="">${phUz}</option>` + uzOpts.filter(Boolean).map(v => `<option value="${escapeHtml(v)}">${escapeHtml(v)}</option>`).join('');
  selRu.innerHTML = `<option value="">${phRu}</option>` + ruOpts.filter(Boolean).map(v => `<option value="${escapeHtml(v)}">${escapeHtml(v)}</option>`).join('');
  if(uzOpts.includes(prevUz)) selUz.value = prevUz;
  if(ruOpts.includes(prevRu)) selRu.value = prevRu;
}

async function loadTests(){
  document.getElementById('testsTbody').innerHTML = `<tr><td colspan="3" class="loading-row">${dict[currentLang].loading}</td></tr>`;
  try{
    const data = await apiCall({ action:'getTestQuestions', token: currentUserForKpi.token });
    if(data.success){
      testsData = data.questions; renderTestsTable();
      document.getElementById('testActiveToggle').checked = data.active;
      document.getElementById('testActiveSub').textContent = data.active ? dict[currentLang].activeOn : dict[currentLang].activeOff;
    }
  } catch(ex){
    document.getElementById('testsTbody').innerHTML = `<tr><td colspan="3" class="loading-row">Xatolik: ${ex.message}</td></tr>`;
  }
}

async function onTestActiveToggle(){
  const cb = document.getElementById('testActiveToggle');
  const desired = cb.checked;
  try{
    const data = await apiCall({ action:'setTestActive', token: currentUserForKpi.token, active: desired });
    if(data.success){
      document.getElementById('testActiveSub').textContent = desired ? dict[currentLang].activeOn : dict[currentLang].activeOff;
    } else {
      cb.checked = !desired;
      alert(dict[currentLang].savedErr);
    }
  } catch(ex){
    cb.checked = !desired;
    alert('Xatolik: ' + ex.message);
  }
}

function renderTestsTable(){
  if(!testsData || testsData.length === 0){
    document.getElementById('testsTbody').innerHTML = `<tr><td colspan="3" class="loading-row">${dict[currentLang].noResults}</td></tr>`;
    return;
  }
  document.getElementById('testsTbody').innerHTML = testsData.map((q, i) => `
    <tr>
      <td>${i + 1}</td>
      <td style="white-space:normal;">${escapeHtml(q.uz.savol)}</td>
      <td><div class="row-actions">
        <button type="button" class="icon-btn" title="${dict[currentLang].editBtn}" onclick="startEditTest('${q.id}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg></button>
        <button type="button" class="icon-btn danger" title="${dict[currentLang].deleteBtn}" onclick="deleteTestRow('${q.id}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/></svg></button>
      </div></td>
    </tr>`).join('');
}

function startEditTest(id){
  const q = testsData.find(t => String(t.id) === String(id));
  if(!q) return;
  editingTestId = id;
  document.getElementById('tSavol').value = q.uz.savol || '';
  document.getElementById('tA').value = q.uz.a || '';
  document.getElementById('tB').value = q.uz.b || '';
  document.getElementById('tC').value = q.uz.c || '';
  document.getElementById('tD').value = q.uz.d || '';
  document.getElementById('tSavolRu').value = q.ru.savol || '';
  document.getElementById('tARu').value = q.ru.a || '';
  document.getElementById('tBRu').value = q.ru.b || '';
  document.getElementById('tCRu').value = q.ru.c || '';
  document.getElementById('tDRu').value = q.ru.d || '';
  refreshCorrectSelects();
  document.getElementById('testFormTitle').textContent = dict[currentLang].testFormEdit;
  document.getElementById('testSubmitBtn').textContent = dict[currentLang].saveBtn;
  document.getElementById('testCancelBtn').style.display = 'inline-flex';
  document.getElementById('testsView').scrollIntoView({ behavior:'smooth', block:'start' });
}

function cancelTestEdit(){
  editingTestId = null;
  document.getElementById('testFormTitle').textContent = dict[currentLang].testFormAdd;
  document.getElementById('testCancelBtn').style.display = 'none';
  document.querySelector('#testsView form').reset();
  refreshCorrectSelects();
}

async function submitTestForm(e){
  e.preventDefault();
  const msg = document.getElementById('testFormMsg'); msg.className = 'msg';
  const payload = {
    token: currentUserForKpi.token,
    action: editingTestId ? 'editTestQuestion' : 'addTestQuestion',
    savol: document.getElementById('tSavol').value, a: document.getElementById('tA').value, b: document.getElementById('tB').value,
    c: document.getElementById('tC').value, d: document.getElementById('tD').value, correct: document.getElementById('tCorrect').value,
    savolRu: document.getElementById('tSavolRu').value, aRu: document.getElementById('tARu').value, bRu: document.getElementById('tBRu').value,
    cRu: document.getElementById('tCRu').value, dRu: document.getElementById('tDRu').value, correctRu: document.getElementById('tCorrectRu').value
  };
  if(editingTestId) payload.id = editingTestId;
  const btn = document.getElementById('testSubmitBtn'); btn.disabled = true;
  try{
    const data = await apiCall(payload);
    btn.disabled = false;
    if(data.success){
      msg.textContent = dict[currentLang].savedOk; msg.classList.add('ok');
      cancelTestEdit();
      loadTests();
    } else {
      msg.textContent = dict[currentLang].savedErr; msg.classList.add('err');
    }
  } catch(ex){ btn.disabled = false; msg.textContent = 'Xatolik: ' + ex.message; msg.classList.add('err'); }
  return false;
}

async function deleteTestRow(id){
  if(!confirm(dict[currentLang].confirmDeleteTest)) return;
  try{
    const data = await apiCall({ action:'deleteTestQuestion', token: currentUserForKpi.token, id });
    if(data.success) loadTests();
  } catch(ex){ alert('Xatolik: ' + ex.message); }
}

/* ---------- Hujjatlar (CRUD) ---------- */
async function loadDocs(){
  document.getElementById('docsTbody').innerHTML = `<tr><td colspan="3" class="loading-row">${dict[currentLang].loading}</td></tr>`;
  try{
    const data = await apiCall({ action:'getDocuments', token: currentUserForKpi.token });
    if(data.success){ docsData = data.docs; renderDocsTable(); }
  } catch(ex){
    document.getElementById('docsTbody').innerHTML = `<tr><td colspan="3" class="loading-row">Xatolik: ${ex.message}</td></tr>`;
  }
}

function renderDocsTable(){
  if(!docsData || docsData.length === 0){
    document.getElementById('docsTbody').innerHTML = `<tr><td colspan="3" class="loading-row">${dict[currentLang].noResults}</td></tr>`;
    return;
  }
  document.getElementById('docsTbody').innerHTML = docsData.map((d, i) => `
    <tr>
      <td>${i + 1}</td>
      <td style="white-space:normal;">${escapeHtml(d.uz)}${d.fromFolder ? ` <span class="badge wait" style="margin-left:6px;" title="${escapeHtml(dict[currentLang].docFromFolderHint)}">${dict[currentLang].docFromFolderBadge}</span>` : ''}</td>
      <td><div class="row-actions">
        <a class="icon-btn" style="text-decoration:none;" title="${dict[currentLang].downloadBtn}" href="${API_BASE_URL.replace('backend/public/index.php','backend/public/document-download.php')}?id=${encodeURIComponent(d.id)}" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg></a>
        <button type="button" class="icon-btn" title="${dict[currentLang].editBtn}" onclick="startEditDoc('${d.id}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg></button>
        ${d.fromFolder ? '' : `<button type="button" class="icon-btn danger" title="${dict[currentLang].deleteBtn}" onclick="deleteDocRow('${d.id}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/></svg></button>`}
      </div></td>
    </tr>`).join('');
}

let pendingDocFileDataUrl = null;

function readFileAsDataUrl(file){
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result);
    reader.onerror = () => reject(new Error('read-failed'));
    reader.readAsDataURL(file);
  });
}

const DOC_MAX_BYTES = 25 * 1024 * 1024;

async function onDocFileChange(e){
  const file = e.target.files[0];
  pendingDocFileDataUrl = null;
  if(!file) return;
  const msg = document.getElementById('docFormMsg'); msg.className = 'msg';
  if(file.size > DOC_MAX_BYTES){
    msg.textContent = dict[currentLang].docFileTooLargeErr; msg.classList.add('err');
    e.target.value = '';
    document.getElementById('docFileBtnText').textContent = dict[currentLang].docFileChoose;
    return;
  }
  if(file.type !== 'application/pdf'){
    msg.textContent = dict[currentLang].docFileTypeErr; msg.classList.add('err');
    e.target.value = '';
    document.getElementById('docFileBtnText').textContent = dict[currentLang].docFileChoose;
    return;
  }
  try{
    pendingDocFileDataUrl = await readFileAsDataUrl(file);
    document.getElementById('docFileBtnText').textContent = file.name;
  } catch(ex){
    msg.textContent = dict[currentLang].docFileTypeErr; msg.classList.add('err');
    e.target.value = '';
    document.getElementById('docFileBtnText').textContent = dict[currentLang].docFileChoose;
  }
}

function startEditDoc(id){
  const d = docsData.find(x => String(x.id) === String(id));
  if(!d) return;
  editingDocId = id;
  pendingDocFileDataUrl = null;
  document.getElementById('dUz').value = d.uz || '';
  document.getElementById('dRu').value = d.ru || '';
  document.getElementById('dFile').value = '';
  document.getElementById('docFileBtnText').textContent = dict[currentLang].docFileChoose;
  const currentEl = document.getElementById('docFileCurrent');
  currentEl.textContent = d.fromFolder ? dict[currentLang].docFromFolderHint : dict[currentLang].docFileKeepCurrent;
  currentEl.style.display = 'block';
  document.getElementById('docFormTitle').textContent = dict[currentLang].docFormEdit;
  document.getElementById('docCancelBtn').style.display = 'inline-flex';
  document.getElementById('docsView').scrollIntoView({ behavior:'smooth', block:'start' });
}

function cancelDocEdit(){
  editingDocId = null;
  pendingDocFileDataUrl = null;
  document.getElementById('docFormTitle').textContent = dict[currentLang].docFormAdd;
  document.getElementById('docCancelBtn').style.display = 'none';
  document.getElementById('docFileCurrent').style.display = 'none';
  document.getElementById('docFileBtnText').textContent = dict[currentLang].docFileChoose;
  document.querySelector('#docsView form').reset();
}

async function submitDocForm(e){
  e.preventDefault();
  const msg = document.getElementById('docFormMsg'); msg.className = 'msg';
  if(!editingDocId && !pendingDocFileDataUrl){
    msg.textContent = dict[currentLang].docFileRequiredErr; msg.classList.add('err');
    return false;
  }
  const payload = {
    token: currentUserForKpi.token,
    action: editingDocId ? 'editDocument' : 'addDocument',
    uz: document.getElementById('dUz').value, ru: document.getElementById('dRu').value,
    file: pendingDocFileDataUrl || ''
  };
  if(editingDocId) payload.id = editingDocId;
  const btn = document.getElementById('docSubmitBtn'); btn.disabled = true;
  try{
    const data = await apiCall(payload);
    btn.disabled = false;
    if(data.success){
      msg.textContent = dict[currentLang].savedOk; msg.classList.add('ok');
      cancelDocEdit();
      loadDocs();
    } else if(data.code === 'FILE_TOO_LARGE'){
      msg.textContent = dict[currentLang].docFileTooLargeErr; msg.classList.add('err');
    } else if(data.code === 'INVALID_FILE'){
      msg.textContent = dict[currentLang].docFileTypeErr; msg.classList.add('err');
    } else {
      msg.textContent = dict[currentLang].savedErr + (data.message ? ': ' + data.message : ''); msg.classList.add('err');
    }
  } catch(ex){ btn.disabled = false; msg.textContent = 'Xatolik: ' + ex.message; msg.classList.add('err'); }
  return false;
}

async function deleteDocRow(id){
  if(!confirm(dict[currentLang].confirmDeleteDoc)) return;
  try{
    const data = await apiCall({ action:'deleteDocument', token: currentUserForKpi.token, id });
    if(data.success) loadDocs();
    else alert(dict[currentLang].savedErr + (data.message ? ': ' + data.message : ''));
  } catch(ex){ alert('Xatolik: ' + ex.message); }
}

/* ---------- Anonim so'rovnoma ---------- */
function openSurveyEntry(){
  if(ANTICOR_MANAGE.includes(currentUserForKpi.rol)){
    openSection('surveyHubView');
    loadSurveyStatusOnly();
  } else {
    openSection('surveyResultsView');
    loadSurveyResults();
  }
}

function backFromSurveyResults(){
  if(ANTICOR_MANAGE.includes(currentUserForKpi.rol)) openSection('surveyHubView');
  else openSection('hubView');
}

async function loadSurveyStatusOnly(){
  try{
    const data = await apiCall({ action:'getSurveyQuestions', token: currentUserForKpi.token });
    if(data.success){
      surveyData = data.questions;
      document.getElementById('surveyActiveToggle').checked = data.active;
      document.getElementById('surveyActiveSub').textContent = data.active ? dict[currentLang].activeOn : dict[currentLang].activeOff;
    }
  } catch(ex){}
}

async function onSurveyActiveToggle(){
  const cb = document.getElementById('surveyActiveToggle');
  const desired = cb.checked;
  try{
    const data = await apiCall({ action:'setSurveyActive', token: currentUserForKpi.token, active: desired });
    if(data.success){
      document.getElementById('surveyActiveSub').textContent = desired ? dict[currentLang].activeOn : dict[currentLang].activeOff;
    } else {
      cb.checked = !desired;
      alert(dict[currentLang].savedErr);
    }
  } catch(ex){
    cb.checked = !desired;
    alert('Xatolik: ' + ex.message);
  }
}

async function loadSurveyQuestions(){
  document.getElementById('surveyTbody').innerHTML = `<tr><td colspan="3" class="loading-row">${dict[currentLang].loading}</td></tr>`;
  try{
    const data = await apiCall({ action:'getSurveyQuestions', token: currentUserForKpi.token });
    if(data.success){
      surveyData = data.questions; renderSurveyTable();
      document.getElementById('surveyActiveToggle').checked = data.active;
      document.getElementById('surveyActiveSub').textContent = data.active ? dict[currentLang].activeOn : dict[currentLang].activeOff;
    }
  } catch(ex){
    document.getElementById('surveyTbody').innerHTML = `<tr><td colspan="3" class="loading-row">Xatolik: ${ex.message}</td></tr>`;
  }
}

function onSurveyTypeChange(){
  const turi = document.getElementById('sTuri').value;
  const isStars = turi === 'yulduz';
  const isChoice = turi === 'tanlov';
  document.getElementById('sStarsField').style.display = isStars ? 'block' : 'none';
  document.getElementById('sOptionsUz').style.display = isChoice ? 'block' : 'none';
  document.getElementById('sOptionsRu').style.display = isChoice ? 'block' : 'none';
  document.getElementById('sA').required = isChoice;
  document.getElementById('sB').required = isChoice;
}

function surveyTypeIcon(turi){
  if(turi === 'yulduz') return '⭐';
  if(turi === 'matn') return '✎';
  return '☰';
}
function surveyTypeLabel(turi){
  if(turi === 'yulduz') return dict[currentLang].qTuriYulduz;
  if(turi === 'matn') return dict[currentLang].qTuriMatn;
  return dict[currentLang].qTuriTanlov;
}

function renderSurveyTable(){
  if(!surveyData || surveyData.length === 0){
    document.getElementById('surveyTbody').innerHTML = `<tr><td colspan="4" class="loading-row">${dict[currentLang].noResults}</td></tr>`;
    return;
  }
  document.getElementById('surveyTbody').innerHTML = surveyData.map((q, i) => `
    <tr>
      <td>${i + 1}</td>
      <td title="${surveyTypeLabel(q.turi)}">${surveyTypeIcon(q.turi)}</td>
      <td style="white-space:normal;">${escapeHtml(q.uz.savol)}</td>
      <td><div class="row-actions">
        <button type="button" class="icon-btn" title="${dict[currentLang].editBtn}" onclick="startEditSurvey('${q.id}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg></button>
        <button type="button" class="icon-btn danger" title="${dict[currentLang].deleteBtn}" onclick="deleteSurveyRow('${q.id}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/></svg></button>
      </div></td>
    </tr>`).join('');
}

function startEditSurvey(id){
  const q = surveyData.find(t => String(t.id) === String(id));
  if(!q) return;
  editingSurveyId = id;
  document.getElementById('sTuri').value = q.turi || 'tanlov';
  document.getElementById('sStars').value = String(q.stars || 5);
  onSurveyTypeChange();
  document.getElementById('sSavol').value = q.uz.savol || '';
  document.getElementById('sA').value = q.uz.a || '';
  document.getElementById('sB').value = q.uz.b || '';
  document.getElementById('sC').value = q.uz.c || '';
  document.getElementById('sD').value = q.uz.d || '';
  document.getElementById('sSavolRu').value = q.ru.savol || '';
  document.getElementById('sARu').value = q.ru.a || '';
  document.getElementById('sBRu').value = q.ru.b || '';
  document.getElementById('sCRu').value = q.ru.c || '';
  document.getElementById('sDRu').value = q.ru.d || '';
  document.getElementById('surveyFormTitle').textContent = dict[currentLang].surveyFormEdit;
  document.getElementById('surveyCancelBtn').style.display = 'inline-flex';
  document.getElementById('surveyQuestionsView').scrollIntoView({ behavior:'smooth', block:'start' });
}

function cancelSurveyEdit(){
  editingSurveyId = null;
  document.getElementById('surveyFormTitle').textContent = dict[currentLang].surveyFormAdd;
  document.getElementById('surveyCancelBtn').style.display = 'none';
  document.querySelector('#surveyQuestionsView form').reset();
  onSurveyTypeChange();
}

async function submitSurveyForm(e){
  e.preventDefault();
  const msg = document.getElementById('surveyFormMsg'); msg.className = 'msg';
  const payload = {
    token: currentUserForKpi.token,
    action: editingSurveyId ? 'editSurveyQuestion' : 'addSurveyQuestion',
    turi: document.getElementById('sTuri').value, stars: document.getElementById('sStars').value,
    savol: document.getElementById('sSavol').value, a: document.getElementById('sA').value, b: document.getElementById('sB').value,
    c: document.getElementById('sC').value, d: document.getElementById('sD').value,
    savolRu: document.getElementById('sSavolRu').value, aRu: document.getElementById('sARu').value, bRu: document.getElementById('sBRu').value,
    cRu: document.getElementById('sCRu').value, dRu: document.getElementById('sDRu').value
  };
  if(editingSurveyId) payload.id = editingSurveyId;
  const btn = document.getElementById('surveySubmitBtn'); btn.disabled = true;
  try{
    const data = await apiCall(payload);
    btn.disabled = false;
    if(data.success){
      msg.textContent = dict[currentLang].savedOk; msg.classList.add('ok');
      cancelSurveyEdit();
      loadSurveyQuestions();
    } else {
      msg.textContent = dict[currentLang].savedErr; msg.classList.add('err');
    }
  } catch(ex){ btn.disabled = false; msg.textContent = 'Xatolik: ' + ex.message; msg.classList.add('err'); }
  return false;
}

async function deleteSurveyRow(id){
  if(!confirm(dict[currentLang].confirmDeleteSurvey)) return;
  try{
    const data = await apiCall({ action:'deleteSurveyQuestion', token: currentUserForKpi.token, id });
    if(data.success) loadSurveyQuestions();
  } catch(ex){ alert('Xatolik: ' + ex.message); }
}

function starsSvg(filled){
  return `<svg viewBox="0 0 24 24" fill="${filled ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" width="17" height="17"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2Z"/></svg>`;
}

const CHART_COLORS = ['#004491', '#1D6FBF', '#FFB020', '#FF6B6B'];

// CSS conic-gradient asosida oddiy "donut" doiraviy diagramma quradi — tashqi
// kutubxonasiz, faqat bitta div orqali.
function buildDonutGradient(segments){
  let acc = 0;
  const stops = segments.map((s, i) => {
    const start = acc; acc += s.pct;
    return `${CHART_COLORS[i % CHART_COLORS.length]} ${start}% ${acc}%`;
  }).join(', ');
  return acc > 0 ? `conic-gradient(${stops})` : 'conic-gradient(var(--input-border) 0% 100%)';
}

function renderChoiceChart(q){
  const opts = q.options.filter(o => o.text);
  const total = q.total || 0;
  const segments = opts.map(o => ({ pct: total ? Math.round(o.count / total * 100) : 0 }));
  const gradient = buildDonutGradient(segments);
  return `<div class="chart-row">
    <div class="donut" style="background:${gradient}"><div class="donut-hole">${total}<br>${dict[currentLang].totalResponsesLbl}</div></div>
    <div class="donut-legend">
      ${opts.map((o, i) => `
        <div class="leg-row">
          <span class="leg-dot" style="background:${CHART_COLORS[i % CHART_COLORS.length]}"></span>
          <span class="leg-label" title="${escapeHtml(o.text)}">${escapeHtml(o.text)}</span>
          <span class="leg-pct">${total ? Math.round(o.count/total*100) : 0}%</span>
        </div>`).join('')}
    </div>
  </div>`;
}

function renderRatingGauge(q){
  const pct = q.stars ? Math.round((q.average / q.stars) * 100) : 0;
  return `<div class="chart-row">
    <div class="gauge" style="background:conic-gradient(var(--amber) 0% ${pct}%, var(--input-bg) ${pct}% 100%)">
      <div class="gauge-hole"><div class="g-num">${q.average || 0}</div><div class="g-sub">/ ${q.stars} ⭐</div></div>
    </div>
    <div class="donut-legend">
      <div class="leg-row"><span class="leg-label">${dict[currentLang].totalResponsesLbl}</span><span class="leg-pct">${q.total}</span></div>
      <div class="leg-row"><span class="leg-label">${dict[currentLang].avgScoreLbl}</span><span class="leg-pct">${pct}%</span></div>
    </div>
  </div>`;
}

async function loadSurveyResults(){
  const box = document.getElementById('surveyResultsBox');
  box.innerHTML = `<p class="loading-row">${dict[currentLang].loading}</p>`;
  try{
    const data = await apiCall({ action:'getSurveyResults', token: currentUserForKpi.token });
    if(!data.success){ box.innerHTML = `<p class="loading-row">Xatolik</p>`; return; }
    surveyResultsData = data;
    if(!data.report || data.report.length === 0){ box.innerHTML = `<p class="loading-row">${dict[currentLang].noResults}</p>`; return; }

    const summaryHtml = `<div class="survey-summary-row">
      <div class="stat-card"><div class="num">${data.totalSubmissions || 0}</div><div class="lbl">${dict[currentLang].totalSubmissionsLbl}</div></div>
      <div class="stat-card teal"><div class="num">${data.report.length}</div><div class="lbl">${dict[currentLang].totalQuestionsLbl}</div></div>
    </div>`;

    const questionsHtml = data.report.map(q => {
      if(q.turi === 'yulduz'){
        const maxCount = Math.max(1, ...q.ratingCounts.map(r => r.count));
        const fullStars = Math.round(q.average);
        return `<div class="result-q">
          <div class="rq-text">${escapeHtml(q.savol)}</div>
          <div class="rq-avg-row">
            <div class="rq-avg-num">${q.average || 0}</div>
            <div class="rq-avg-stars">${Array.from({length:q.stars}).map((_,i)=>starsSvg(i<fullStars)).join('')}</div>
          </div>
          ${q.total > 0 ? renderRatingGauge(q) : ''}
          <div class="section-divider">${dict[currentLang].byQuestionLbl}</div>
          ${q.ratingCounts.map(r => `
            <div class="result-opt-row">
              <div class="ro-label">${r.value} ⭐</div>
              <div class="ro-bar-wrap"><div class="ro-bar star-bar" style="width:${Math.round(r.count/maxCount*100)}%"></div></div>
              <div class="ro-count">${r.count}</div>
            </div>`).join('')}
          <div class="rq-total">${q.total} ${dict[currentLang].totalResponsesLbl}</div>
        </div>`;
      }
      if(q.turi === 'matn'){
        return `<div class="result-q">
          <div class="rq-text">${escapeHtml(q.savol)}</div>
          ${q.responses.length === 0
            ? `<p style="color:var(--text-dim); font-size:12.5px;">${dict[currentLang].resultsEmpty}</p>`
            : `<div class="text-answers">${q.responses.map(t => `<div class="text-answer-item">${escapeHtml(t)}</div>`).join('')}</div>`}
          <div class="rq-total">${q.total} ${dict[currentLang].totalResponsesLbl}</div>
        </div>`;
      }
      const maxCount = Math.max(1, ...q.options.map(o => o.count));
      return `<div class="result-q">
        <div class="rq-text">${escapeHtml(q.savol)}</div>
        ${q.total > 0 ? renderChoiceChart(q) : ''}
        <div class="section-divider">${dict[currentLang].byQuestionLbl}</div>
        ${q.options.filter(o => o.text).map(o => `
          <div class="result-opt-row">
            <div class="ro-label" title="${escapeHtml(o.text)}">${escapeHtml(o.text)}</div>
            <div class="ro-bar-wrap"><div class="ro-bar" style="width:${Math.round(o.count/maxCount*100)}%"></div></div>
            <div class="ro-count">${o.count} <span class="ro-pct">(${q.total ? Math.round(o.count/q.total*100) : 0}%)</span></div>
          </div>`).join('')}
        <div class="rq-total">${q.total} ${dict[currentLang].totalResponsesLbl}</div>
      </div>`;
    }).join('');

    box.innerHTML = summaryHtml + questionsHtml;
  } catch(ex){
    box.innerHTML = `<p class="loading-row">Xatolik: ${ex.message}</p>`;
  }
}

/* ---------- Xabarnoma yuborish ---------- */
let notifSelectedLogins = new Set();
let notifHistoryLoaded = false;

function onNotifTargetTypeChange(){
  const type = document.getElementById('notifTargetType').value;
  document.getElementById('notifUsersField').style.display = type === 'users' ? 'block' : 'none';
  document.getElementById('notifDeptField').style.display = type === 'department' ? 'block' : 'none';
  if(type === 'department' && document.getElementById('notifDeptSelect').options.length === 0){
    const depts = [...new Set((usersData||[]).map(u=>u.bolinma).filter(Boolean))];
    document.getElementById('notifDeptSelect').innerHTML = depts.map(d => `<option value="${escapeHtml(d)}">${escapeHtml(d)}</option>`).join('');
  }
}

function renderNotifUserList(){
  const q = document.getElementById('notifUserSearch').value.trim().toLowerCase();
  const list = (usersData||[]).filter(u=>{
    const fish = [u.familiya,u.ism,u.otasi].filter(Boolean).join(' ').toLowerCase();
    return !q || fish.includes(q);
  });
  document.getElementById('notifUserList').innerHTML = list.map(u=>{
    const fish = [u.familiya,u.ism,u.otasi].filter(Boolean).join(' ');
    const checked = notifSelectedLogins.has(u.login) ? 'checked' : '';
    return `<label class="notif-user-opt"><input type="checkbox" ${checked} onchange="toggleNotifUser('${u.login}', this.checked)"> ${escapeHtml(fish)}</label>`;
  }).join('') || `<p style="color:var(--text-dim); font-size:12.5px; padding:6px;">${dict[currentLang].noResults}</p>`;
}

function toggleNotifUser(login, checked){
  if(checked) notifSelectedLogins.add(login); else notifSelectedLogins.delete(login);
}

async function submitNotification(e){
  e.preventDefault();
  const msg = document.getElementById('notifSendMsg'); msg.className = 'msg';
  const type = document.getElementById('notifTargetType').value;
  const text = document.getElementById('notifText').value.trim();
  const payload = { action:'sendNotification', token: currentUserForKpi.token, targetType: type, text };
  if(type === 'department'){
    payload.department = document.getElementById('notifDeptSelect').value;
    if(!payload.department){ msg.textContent = dict[currentLang].notifNoTarget; msg.classList.add('err'); return false; }
  } else {
    payload.targetLogins = Array.from(notifSelectedLogins);
    if(payload.targetLogins.length === 0){ msg.textContent = dict[currentLang].notifNoTarget; msg.classList.add('err'); return false; }
  }
  const btn = e.target.querySelector('.submit-btn'); btn.disabled = true;
  try{
    const data = await apiCall(payload);
    btn.disabled = false;
    if(data.success){
      msg.textContent = dict[currentLang].savedOk; msg.classList.add('ok');
      e.target.reset();
      notifSelectedLogins.clear();
      renderNotifUserList();
      notifHistoryLoaded = false;
      loadNotifHistory();
    } else {
      msg.textContent = dict[currentLang].savedErr; msg.classList.add('err');
    }
  } catch(ex){ btn.disabled = false; msg.textContent = 'Xatolik: ' + ex.message; msg.classList.add('err'); }
  return false;
}

async function loadNotifHistory(){
  if(notifHistoryLoaded) return;
  notifHistoryLoaded = true;
  try{
    const data = await apiCall({ action:'getNotificationReport', token: currentUserForKpi.token });
    if(data.success) renderNotifHistory(data.report);
  } catch(ex){
    document.getElementById('notifHistoryList').innerHTML = `<p style="color:var(--coral); font-size:13px;">Xatolik: ${ex.message}</p>`;
  }
}

function renderNotifHistory(report){
  const box = document.getElementById('notifHistoryList');
  if(!report || report.length === 0){
    box.innerHTML = `<p style="color:var(--text-dim); font-size:13px;">${dict[currentLang].notifNoHistory}</p>`;
    return;
  }
  box.innerHTML = report.map((n, i) => {
    const targetLabel = n.targetType === 'department' ? (dict[currentLang].notifTargetDept + ': ' + escapeHtml(n.targetValue)) : (dict[currentLang].notifTargetUsers + ' (' + n.totalTarget + ')');
    const readers = n.readers.map(r => `<div>${escapeHtml(r.fish)} — ${escapeHtml(r.sana)}</div>`).join('') || `<div>${dict[currentLang].notifNoReaders}</div>`;
    return `<div class="notif-hist-item" onclick="document.getElementById('nh-${i}').classList.toggle('show')">
      <div class="h-meta"><span>${escapeHtml(n.senderFish)} • ${targetLabel}</span><span>${escapeHtml(n.sana)}</span></div>
      <div class="h-text">${escapeHtml(n.text)}</div>
      <div class="h-stat">${dict[currentLang].notifReadStat}: ${n.readCount}/${n.totalTarget}</div>
      <div class="notif-readers" id="nh-${i}">${readers}</div>
    </div>`;
  }).join('');
}

/* ---------- Yordam so'rovlari (Support) ---------- */
let supportData = null;
let currentSupportDetailId = null;
let supportSelected = new Set();

/* ---------- Xodimlar yuborgan hujjatlar (Inson resurslari) ---------- */
let hrDocsData = null;

async function loadHrDocuments(){
  const tbody = document.getElementById('hrDocsTbody');
  try{
    const data = await apiCall({ action:'getHrDocuments', token: currentUserForKpi.token });
    if(data.success){ hrDocsData = data.documents; renderHrDocsTable(); }
    else if(tbody) tbody.innerHTML = `<tr><td colspan="6" class="loading-row">Xatolik</td></tr>`;
  } catch(ex){
    if(tbody) tbody.innerHTML = `<tr><td colspan="6" class="loading-row">Xatolik: ${escapeHtml(ex.message)}</td></tr>`;
  }
}

function renderHrDocsTable(){
  const tbody = document.getElementById('hrDocsTbody');
  if(!tbody) return;
  if(!hrDocsData || hrDocsData.length === 0){
    tbody.innerHTML = `<tr><td colspan="6" class="loading-row">${dict[currentLang].noResults}</td></tr>`;
    return;
  }
  tbody.innerHTML = hrDocsData.map(d => `
    <tr>
      <td title="${escapeHtml(d.fish)}">${escapeHtml(d.fish)}</td>
      <td>${escapeHtml(d.telefon)||'—'}</td>
      <td>${escapeHtml(d.lavozim)||'—'}</td>
      <td>${escapeHtml(d.bolinma)||'—'}</td>
      <td>${escapeHtml(d.sana)}</td>
      <td>
        <a class="icon-btn" style="text-decoration:none;" title="${dict[currentLang].downloadBtn}" href="${API_BASE_URL.replace('backend/public/index.php','backend/public/hr-document-download.php')}?id=${encodeURIComponent(d.id)}" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg></a>
        <button type="button" class="icon-btn danger" title="${dict[currentLang].deleteBtn}" onclick="deleteHrDocumentRow(${d.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg></button>
      </td>
    </tr>
  `).join('');
}

async function deleteHrDocumentRow(id){
  if(!confirm(dict[currentLang].confirmDeleteHrDoc)) return;
  try{
    const data = await apiCall({ action:'deleteHrDocument', token: currentUserForKpi.token, id });
    if(data.success){ loadHrDocuments(); }
    else alert(dict[currentLang].savedErr);
  } catch(ex){ alert('Xatolik: ' + ex.message); }
}

/* ---------- Manfaatlar to'qnashuvi (yuborilgan deklaratsiyalar) ----------
   Hozircha faqat o'zbek tilida ko'rsatiladi (admin panelning o'zi ham
   asosan shu tilda) — savol matnlari shu bois pastda qayta e'lon qilingan
   (main.html'dagi DECL_T'dan mustaqil). */
let declarationsData = [];
let currentDeclarationView = null;

const ADMIN_DECL_QUESTIONS = [
  "Siz boshqaruv organi (boshqaruv, Kuzatuv kengashi, direktorlar kengashi va hokazolar) xodimi, a'zosi, qandaydir tashkilot direktori (bosh buxgalteri, buxgalteri va hokazo) yoki vakilimisiz?",
  "Sizda / yaqin qarindoshlaringizda qandaydir tashkilotlarda moliyaviy manfaatdorlik bormi (ustav kapitalida ishtirok, aksiya va obligatsiyalarga egalik) yoki bunday tashkilotlar qarorlariga boshqa tarzda ta'sir ko'rsata olasizmi?",
  "Yaqin qarindoshlaringiz boshqaruv organlari (boshqaruv, kuzatuv kengashi, direktorlar kengashi va h.k.) xodimi, a'zosi, tashkilot direktori yoki vakilimi?",
  "Yaqin qarindoshlaringiz davlat organlarining mansabdor shaxsi hisoblanadimi?",
  "Shaxsiy manfaatlaringiz, yaqin qarindoshlaringiz yoki aloqador shaxslar manfaatlari yo'lida maxfiy hisoblangan, davlat organlari va tashkilotlarida ishlash davomida ma'lum bo'lgan axborotdan foydalanganmisiz?",
  "Manfaatlar to'qnashuviga olib kelishi mumkin bo'lgan boshqa shart-sharoitlar mavjud bo'lsa, ularni ko'rsatib o'ting.",
  "Zarur topsangiz, har qanday qo'shimcha ma'lumotni ko'rsating.",
];

const ADMIN_DECL_HOLAT_LABELS = {
  info: "To'ldirildi", no_info: "Ma'lumotga ega emas", deceased: "Vafot etgan", no_contact: "Ajrashgan / aloqada emas",
};

async function loadDeclarations(){
  const tbody = document.getElementById('declarationsTbody');
  try{
    const data = await apiCall({ action:'getDeclarations', token: currentUserForKpi.token });
    if(data.success){
      declarationsData = data.declarations;
      renderDeclarationsTable();
    } else {
      tbody.innerHTML = `<tr><td colspan="7" class="loading-row">${dict[currentLang].savedErr}</td></tr>`;
    }
  } catch(ex){
    tbody.innerHTML = `<tr><td colspan="7" class="loading-row">Xatolik: ${escapeHtml(ex.message)}</td></tr>`;
  }
}

function renderDeclarationsTable(){
  const tbody = document.getElementById('declarationsTbody');
  if(!declarationsData || declarationsData.length === 0){
    tbody.innerHTML = `<tr><td colspan="7" class="loading-row">${dict[currentLang].noResults}</td></tr>`;
    return;
  }
  // O'chirish faqat ANTICOR_MANAGE (anticor-admin/super-admin) huquqiga ega
  // — "anticor" (faqat ko'rish) roli backend'da ham rad etiladi, shu bois
  // tugmani ularga ko'rsatmaymiz (boshqa jadvallardagi kabi bir xil andoza).
  const canDelete = currentUserForKpi && ANTICOR_MANAGE.includes(currentUserForKpi.rol);
  tbody.innerHTML = declarationsData.map((d, i) => {
    const conflictBadge = d.hasConflict === true ? `<span class="badge no">MAVJUD</span>`
      : d.hasConflict === false ? `<span class="badge ok">MAVJUD EMAS</span>`
      : `<span class="badge wait">—</span>`;
    return `
    <tr>
      <td>${i + 1}</td>
      <td>${escapeHtml(d.fullName || '—')}</td>
      <td>${escapeHtml(d.lavozim || '—')}</td>
      <td>${escapeHtml(d.bolinma || '—')}</td>
      <td>${escapeHtml(d.telefon || '—')}</td>
      <td>${conflictBadge}</td>
      <td>
        <button type="button" class="icon-btn" title="Ko'rish" onclick="viewDeclarationRow(${d.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg></button>
        ${canDelete ? `<button type="button" class="icon-btn danger" title="O'chirish" onclick="deleteDeclarationRow(${d.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/></svg></button>` : ''}
      </td>
    </tr>`;
  }).join('');
}

async function deleteDeclarationRow(id){
  if(!confirm("Bu deklaratsiyani butunlay o'chirishni tasdiqlaysizmi?")) return;
  try{
    const data = await apiCall({ action:'deleteDeclaration', token: currentUserForKpi.token, id });
    if(data.success){ loadDeclarations(); }
    else alert(dict[currentLang].savedErr);
  } catch(ex){ alert('Xatolik: ' + ex.message); }
}

async function viewDeclarationRow(id){
  currentDeclarationView = null;
  const body = document.getElementById('declModalViewBody');
  document.getElementById('declModalViewTitle').textContent = 'Deklaratsiya';
  body.innerHTML = '<p style="color:var(--text-dim); font-size:13px;">Yuklanmoqda...</p>';
  openModal('declarationViewModal');
  try{
    const data = await apiCall({ action:'getDeclaration', token: currentUserForKpi.token, id });
    if(!data.success){ body.innerHTML = `<p style="color:var(--coral); font-size:13px;">${dict[currentLang].savedErr}</p>`; return; }
    renderDeclarationView(data.declaration);
  } catch(ex){
    body.innerHTML = `<p style="color:var(--coral); font-size:13px;">Xatolik: ${escapeHtml(ex.message)}</p>`;
  }
}

function declInfoRow(label, valueHtml){
  return `<div style="display:flex; justify-content:space-between; gap:12px; padding:9px 0; border-top:1px solid var(--card-border); font-size:13px;"><span style="color:var(--text-dim);">${escapeHtml(label)}</span><span style="text-align:right; font-weight:600;">${valueHtml}</span></div>`;
}

function renderDeclarationView(d){
  currentDeclarationView = d;
  const p = d.payload || {};
  const relatives = Array.isArray(p.relatives) ? p.relatives : [];
  const answers = Array.isArray(p.answers) ? p.answers : [];
  const jshshir = p.employeeJshshir || '—';

  document.getElementById('declModalViewTitle').textContent = d.refId || 'Deklaratsiya';

  const relRows = relatives.map(r => {
    const rd = r.data;
    const nameCell = (rd && rd.holat === 'info' && rd.fullName) ? escapeHtml(rd.fullName)
      : `— <span class="badge wait" style="margin-left:4px;">${escapeHtml((rd && ADMIN_DECL_HOLAT_LABELS[rd.holat]) || "ma'lumot yo'q")}</span>`;
    return `<tr><td style="padding:7px 8px; border-top:1px solid var(--card-border);"><b>${escapeHtml(r.label)}</b></td><td style="padding:7px 8px; border-top:1px solid var(--card-border);">${nameCell}</td></tr>`;
  }).join('');

  const qRows = ADMIN_DECL_QUESTIONS.map((q, i) => {
    const a = answers[i] || {};
    let badge = '<span style="color:var(--text-dim);">—</span>';
    if(i < 5 && a.choice){
      badge = `<span class="badge ${a.choice === 'ha' ? 'no' : 'ok'}">${a.choice === 'ha' ? 'HA' : "YO'Q"}</span>`;
    }
    const noteHtml = a.note ? `<div style="color:var(--text-dim); font-size:12px; margin-top:4px;">${escapeHtml(a.note)}</div>` : '';
    return `<div style="padding:10px 0; border-top:1px solid var(--card-border);"><div style="font-size:13px;">${i + 1}. ${escapeHtml(q)}</div><div style="margin-top:6px;">${badge}</div>${noteHtml}</div>`;
  }).join('');

  const conflictText = p.confirm && p.confirm.hasConflict === true ? 'MAVJUD' : (p.confirm && p.confirm.hasConflict === false ? 'MAVJUD EMAS' : '—');
  const conflictClass = p.confirm && p.confirm.hasConflict === true ? 'no' : 'ok';

  document.getElementById('declModalViewBody').innerHTML = `
    ${declInfoRow('F.I.Sh.', escapeHtml(d.fullName || '—'))}
    ${declInfoRow('JShShIR', escapeHtml(jshshir))}
    ${declInfoRow('Lavozim', escapeHtml(d.lavozim || '—'))}
    ${declInfoRow("Bo'linma", escapeHtml(d.bolinma || '—'))}
    ${declInfoRow('Tel raqam', escapeHtml(d.telefon || '—'))}
    ${declInfoRow('Yuborilgan sana', escapeHtml(d.submittedAt || '—'))}
    ${declInfoRow('Manfaatlar to\'qnashuvi', `<span class="badge ${conflictClass}">${conflictText}</span>`)}
    ${declInfoRow('Verification ID', `<span style="font-family:monospace;">${escapeHtml(d.verificationId || '—')}</span>`)}

    <h3 style="margin:18px 0 8px; font-size:14px; font-family:'Unbounded', sans-serif;">Yaqin qarindoshlar</h3>
    <table style="width:100%; border-collapse:collapse; font-size:13px;"><tbody>${relRows}</tbody></table>

    <h3 style="margin:18px 0 6px; font-size:14px; font-family:'Unbounded', sans-serif;">Savollar va javoblar</h3>
    ${qRows}
  `;
}

function declAdminWorkplaceText(rd){
  if(!rd || rd.holat !== 'info') return '—';
  if(rd.workType === 'other') return rd.workplacePosition || '—';
  if(rd.workType === 'entrepreneur') return rd.legalEntityName || '—';
  return '—';
}

const DECL_ADMIN_QR_SVG = `<svg viewBox="0 0 100 100" width="100" height="100">
  <rect width="100" height="100" fill="none"/>
  <rect x="6" y="6" width="22" height="22" fill="none" stroke="var(--azure)" stroke-width="6"/>
  <rect x="13" y="13" width="8" height="8" fill="var(--azure)"/>
  <rect x="72" y="6" width="22" height="22" fill="none" stroke="var(--azure)" stroke-width="6"/>
  <rect x="79" y="13" width="8" height="8" fill="var(--azure)"/>
  <rect x="6" y="72" width="22" height="22" fill="none" stroke="var(--azure)" stroke-width="6"/>
  <rect x="13" y="79" width="8" height="8" fill="var(--azure)"/>
  <rect x="40" y="10" width="7" height="7" fill="var(--azure)"/>
  <rect x="52" y="18" width="7" height="7" fill="var(--azure)"/>
  <rect x="40" y="28" width="7" height="7" fill="var(--azure)"/>
  <rect x="60" y="40" width="7" height="7" fill="var(--azure)"/>
  <rect x="44" y="48" width="7" height="7" fill="var(--azure)"/>
  <rect x="72" y="48" width="7" height="7" fill="var(--azure)"/>
  <rect x="10" y="44" width="7" height="7" fill="var(--azure)"/>
  <rect x="80" y="64" width="7" height="7" fill="var(--azure)"/>
  <rect x="40" y="72" width="7" height="7" fill="var(--azure)"/>
  <rect x="56" y="80" width="7" height="7" fill="var(--azure)"/>
  <rect x="72" y="80" width="7" height="7" fill="var(--azure)"/>
</svg>`;

/* Xodim tomonidan ko'rilgan "Deklaratsiya" yozuv sahifasi (main.html'dagi
   declRenderRecord() — QR-kod va E-IMZO blokini o'z ichiga olgan yakuniy
   imzolangan variant) bilan bir xil ko'rinishdagi, alohida (o'z uslubiga
   ega) hujjatni yangi tabda ochadi — admin shu yerdan "Chop etish / PDF"
   qila oladi. Chop etishda har doim o'qiladigan bo'lishi uchun (qorong'u
   mavzudan qat'i nazar) doim och (light) ranglar bilan quriladi. */
function downloadDeclarationRecord(){
  const d = currentDeclarationView;
  if(!d){ alert("Deklaratsiya ma'lumotlari hali yuklanmadi"); return; }
  const p = d.payload || {};
  const relatives = Array.isArray(p.relatives) ? p.relatives : [];
  const answers = Array.isArray(p.answers) ? p.answers : [];
  const fullName = d.fullName || '—';
  const lavozim = d.lavozim || '—';

  const relRows = relatives.map(r => {
    const rd = r.data;
    const nameCell = (rd && rd.holat === 'info' && rd.fullName)
      ? escapeHtml(rd.fullName)
      : `<span class="decl-pill gray">${escapeHtml((rd && ADMIN_DECL_HOLAT_LABELS[rd.holat]) || "ma'lumot yo'q")}</span>`;
    const jshCell = (rd && rd.holat === 'info' && rd.jshshir) ? escapeHtml(rd.jshshir) : '—';
    const addrCell = (rd && rd.holat === 'info' && rd.address) ? escapeHtml(rd.address) : '—';
    return `<tr><td><b>${escapeHtml(r.label || '—')}</b></td><td>${nameCell}</td><td>${jshCell}</td><td>${escapeHtml(declAdminWorkplaceText(rd))}</td><td>${addrCell}</td></tr>`;
  }).join('');

  const qBlocks = ADMIN_DECL_QUESTIONS.map((q, i) => {
    const a = answers[i] || {};
    let badge = '';
    if(i < 5 && a.choice){
      badge = `<div style="margin-top:6px;"><span class="decl-pill ${a.choice === 'ha' ? 'coral' : 'teal'}">${a.choice === 'ha' ? 'HA' : "YO'Q"}</span></div>`;
    }
    const noteHtml = a.note ? `<div style="color:var(--text-dim); font-size:12px; margin-top:6px; line-height:1.5;">${escapeHtml(a.note)}</div>` : '';
    return `<div class="decl-record-q">
      <div style="display:flex; gap:10px; align-items:flex-start;">
        <div class="decl-q-num">${i + 1}</div>
        <div style="flex:1;">
          <div style="font-size:13px; line-height:1.5;">${escapeHtml(q)}</div>
          ${badge}${noteHtml}
        </div>
      </div>
    </div>`;
  }).join('');

  const conflictBadge = d.hasConflict === true
    ? `<span class="decl-pill coral">Manfaatlar to'qnashuviga olib keladigan holatlar — MAVJUD</span>`
    : `<span class="decl-pill teal">Manfaatlar to'qnashuviga olib keladigan holatlar — MAVJUD EMAS</span>`;

  // submittedAt backend'dan "YYYY-MM-DD HH:MM" shaklida keladi — "DD.MM.YYYY · HH:MM" ko'rinishiga o'tkazamiz.
  const dtMatch = /^(\d{4})-(\d{2})-(\d{2}) (\d{2}:\d{2})$/.exec(d.submittedAt || '');
  const submittedDateOnly = dtMatch ? `${dtMatch[3]}.${dtMatch[2]}.${dtMatch[1]}` : (d.submittedAt || '—');
  const signedAtDisplay = dtMatch ? `${submittedDateOnly} · ${dtMatch[4]}` : (d.submittedAt || '—');

  const bodyHtml = `
    <div class="decl-record-eyebrow">${escapeHtml(d.refId || 'DEK-2026')}</div>
    <div class="decl-record-head">
      <h1 class="decl-title">Deklaratsiya</h1>
      <button class="decl-btn primary no-print" onclick="window.print()">PDF / Chop etish</button>
    </div>
    <div class="decl-card" style="margin-top:20px;">
      <div class="decl-review-row"><span>F.I.Sh.</span><b>${escapeHtml(fullName)}</b></div>
      <div class="decl-review-row"><span>JShShIR</span><b>${escapeHtml(p.employeeJshshir || '—')}</b></div>
      <div class="decl-review-row"><span>Tarkibiy bo'linma nomi</span><b>${escapeHtml(d.bolinma || '—')}</b></div>
      <div class="decl-review-row"><span>Lavozim</span><b>${escapeHtml(lavozim)}</b></div>
      <div class="decl-review-row"><span>Turi / sababi</span><b>Yangi deklaratsiya</b></div>
      <div class="decl-review-row"><span>To'ldirilgan sana</span><b>${escapeHtml(submittedDateOnly)}</b></div>
      <div class="decl-review-row"><span>Status</span><span class="decl-pill teal">Yuborilgan</span></div>
    </div>

    <div class="decl-card" style="margin-top:16px;">
      <h2>Yaqin qarindoshlar</h2>
      <div style="overflow-x:auto;">
        <table class="decl-review-table">
          <thead><tr><th>Daraja</th><th>F.I.Sh.</th><th>JShShIR</th><th>Ish joyi</th><th>Manzil</th></tr></thead>
          <tbody>${relRows}</tbody>
        </table>
      </div>
    </div>

    <div class="decl-card" style="margin-top:16px;">
      <h2>Savollar va javoblar</h2>
      ${qBlocks}
      <div style="margin-top:14px;">${conflictBadge}</div>
    </div>

    <div class="decl-card decl-esign-block" style="margin-top:16px;">
      <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap;">
        <div style="display:flex; align-items:center; gap:10px;">
          <div class="decl-esign-icon">&check;</div>
          <h2 style="margin-bottom:0;">Elektron imzo (E-IMZO)</h2>
        </div>
        <span class="decl-pill teal">Imzo tasdiqlangan</span>
      </div>
      <div style="display:flex; gap:24px; margin-top:18px; flex-wrap:wrap;">
        <div style="flex:1; min-width:260px;">
          <div class="decl-esign-row"><span>Imzolovchi F.I.Sh.</span><b>${escapeHtml(fullName)}</b></div>
          <div class="decl-esign-row"><span>Lavozimi</span><b>${escapeHtml(lavozim)}</b></div>
          <div class="decl-esign-row"><span>Imzolangan sana va vaqt</span><b>${escapeHtml(signedAtDisplay)}</b></div>
          <div class="decl-esign-row"><span>E-IMZO sertifikati</span><b>DS E-1042-2026 · «O'zbektelekom» AK UC<br>amal qilish: 31.12.2026 gacha</b></div>
          <div class="decl-esign-row"><span>Imzo holati</span><b style="color:var(--teal);">&check; Haqiqiy, hujjat o'zgartirilmagan</b></div>
          <div class="decl-esign-row"><span>Verification ID</span><span class="decl-pill gray" style="font-family:monospace;">${escapeHtml(d.verificationId || '—')}</span></div>
        </div>
        <div class="decl-qr-box">
          <div class="decl-qr-placeholder">${DECL_ADMIN_QR_SVG}</div>
          <div style="font-size:11px; color:var(--text-dim); text-align:center; margin-top:8px;">QR orqali imzoni tekshirish</div>
        </div>
      </div>
    </div>
  `;

  const doc = `<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<title>${escapeHtml(d.refId || 'Deklaratsiya')}</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Unbounded:wght@700;800&family=Inter:wght@400;500;600;700&display=swap');
:root{
  --azure:#004491; --teal:#1D6FBF; --coral:#FF6B6B;
  --bg:#F1F2F4; --bg-deep:#E4E6E9; --card-border:rgba(11,37,69,0.14);
  --text:#0B2545; --text-dim:#4E6A88;
}
*{box-sizing:border-box; margin:0; padding:0;}
body{ font-family:'Inter', sans-serif; color:var(--text); background:#fff; padding:28px 32px 60px; max-width:900px; margin:0 auto; }
.decl-record-eyebrow{ font-size:11.5px; font-weight:700; letter-spacing:1.2px; text-transform:uppercase; color:var(--azure); margin-bottom:8px; }
.decl-record-head{ display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap; }
.decl-title{ font-family:'Unbounded', sans-serif; font-weight:800; font-size:26px; margin:0; }
.decl-btn{ padding:11px 20px; border-radius:10px; font-weight:700; font-size:13px; cursor:pointer; border:none; font-family:'Inter'; }
.decl-btn.primary{ background:linear-gradient(135deg, var(--azure), var(--teal)); color:#fff; }
.decl-card{ background:#fff; border:1px solid var(--card-border); border-radius:16px; padding:26px; }
.decl-card h2{ font-family:'Unbounded', sans-serif; font-size:15px; margin-bottom:12px; }
.decl-esign-block{ background:rgba(29,111,191,0.05); border-color:rgba(29,111,191,0.25); }
.decl-esign-icon{ width:28px; height:28px; border-radius:8px; background:var(--teal); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:14px; flex:none; }
.decl-esign-row{ display:grid; grid-template-columns:180px 1fr; gap:10px; padding:8px 0; border-top:1px solid rgba(29,111,191,0.15); font-size:12.5px; align-items:baseline; }
.decl-esign-row:first-child{ border-top:none; }
.decl-esign-row span{ color:var(--text-dim); }
.decl-esign-row b{ font-weight:700; }
.decl-qr-box{ flex:none; display:flex; flex-direction:column; align-items:center; width:140px; padding-left:22px; border-left:1px solid rgba(29,111,191,0.15); }
.decl-qr-placeholder{ width:100px; height:100px; border-radius:10px; background:var(--bg); border:1px solid var(--card-border); }
.decl-review-row{ display:flex; justify-content:space-between; gap:12px; padding:8px 0; border-top:1px solid var(--card-border); font-size:13px; }
.decl-review-row:first-of-type{ border-top:none; }
.decl-review-row span{ color:var(--text-dim); }
.decl-review-table{ width:100%; border-collapse:collapse; font-size:12.5px; }
.decl-review-table th{ text-align:left; padding:8px 10px; color:var(--text-dim); font-weight:600; font-size:10.5px; text-transform:uppercase; letter-spacing:.3px; border-bottom:1px solid var(--card-border); }
.decl-review-table td{ padding:9px 10px; border-bottom:1px solid var(--card-border); }
.decl-review-table tr:last-child td{ border-bottom:none; }
.decl-record-q{ padding:12px 0; border-top:1px solid var(--card-border); }
.decl-record-q:first-of-type{ border-top:none; padding-top:6px; }
.decl-q-num{ flex:none; width:24px; height:24px; border-radius:50%; background:var(--azure); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:11px; }
.decl-pill{ display:inline-block; font-size:11px; font-weight:700; padding:3px 10px; border-radius:999px; white-space:nowrap; }
.decl-pill.gray{ background:var(--bg-deep); color:var(--text-dim); }
.decl-pill.teal{ background:rgba(29,111,191,0.15); color:var(--teal); }
.decl-pill.coral{ background:rgba(255,107,107,0.15); color:var(--coral); }
@media print{ .no-print{ display:none !important; } body{ padding:0; } }
@page{ margin:14mm; }
</style>
</head>
<body>
${bodyHtml}
</body>
</html>`;

  const win = window.open('', '_blank');
  if(!win){ alert("Yangi oyna ochilmadi — brauzeringiz popup'larni bloklagan bo'lishi mumkin"); return; }
  win.document.open();
  win.document.write(doc);
  win.document.close();
}

async function loadSupportRequests(){
  const tbody = document.getElementById('supportTbody');
  try{
    const data = await apiCall({ action:'getSupportRequests', token: currentUserForKpi.token });
    if(data.success){ supportData = data.requests; supportSelected = new Set(); renderSupportTable(); updateSupportBadge(); }
    else if(tbody) tbody.innerHTML = `<tr><td colspan="6" class="loading-row">Xatolik</td></tr>`;
  } catch(ex){
    if(tbody) tbody.innerHTML = `<tr><td colspan="6" class="loading-row">Xatolik: ${escapeHtml(ex.message)}</td></tr>`;
  }
}

function updateSupportBadge(){
  const badge = document.getElementById('supportBadge');
  if(!badge || !supportData) return;
  const unreviewed = supportData.filter(r => !r.comments || r.comments.length === 0).length;
  if(unreviewed > 0){ badge.textContent = unreviewed > 99 ? '99+' : String(unreviewed); badge.style.display = 'flex'; badge.style.alignItems = 'center'; badge.style.justifyContent = 'center'; }
  else badge.style.display = 'none';
}

function toggleSupportRow(id, checked){
  if(checked) supportSelected.add(id); else supportSelected.delete(id);
  updateSupportBulkBar();
}

function toggleAllSupport(){
  const checked = document.getElementById('supportSelectAll').checked;
  supportSelected = new Set(checked && supportData ? supportData.map(r => r.id) : []);
  renderSupportTable();
}

function updateSupportBulkBar(){
  const canDelete = currentUserForKpi && ANTICOR_MANAGE.includes(currentUserForKpi.rol);
  const bar = document.getElementById('supportBulkBar');
  const th = document.getElementById('supportSelectAllTh');
  if(bar) bar.style.display = canDelete ? 'flex' : 'none';
  if(th) th.style.display = canDelete ? 'table-cell' : 'none';
  const countEl = document.getElementById('supportSelectedCount');
  if(countEl) countEl.textContent = supportSelected.size;
  const btn = document.getElementById('supportDeleteBtn');
  if(btn) btn.disabled = supportSelected.size === 0;
  const selectAll = document.getElementById('supportSelectAll');
  if(selectAll) selectAll.checked = !!(supportData && supportData.length && supportSelected.size === supportData.length);
}

async function deleteSelectedSupport(){
  if(!supportSelected.size) return;
  if(!confirm(dict[currentLang].confirmBulkDelete.replace('{n}', supportSelected.size))) return;
  try{
    const data = await apiCall({ action:'deleteSupportRequests', token: currentUserForKpi.token, ids: Array.from(supportSelected) });
    if(!data.success){ alert(dict[currentLang].savedErr); return; }
    supportSelected = new Set();
    await loadSupportRequests();
  } catch(ex){ alert('Xatolik: ' + ex.message); }
}

function renderSupportTable(){
  const tbody = document.getElementById('supportTbody');
  if(!tbody) return;
  const canDelete = currentUserForKpi && ANTICOR_MANAGE.includes(currentUserForKpi.rol);
  updateSupportBulkBar();
  if(!supportData || supportData.length === 0){
    tbody.innerHTML = `<tr><td colspan="7" class="loading-row">${dict[currentLang].noResults}</td></tr>`;
    return;
  }
  tbody.innerHTML = supportData.map(r => `
    <tr>
      ${canDelete ? `<td><input type="checkbox" ${supportSelected.has(r.id)?'checked':''} onchange="toggleSupportRow(${r.id}, this.checked)"></td>` : ''}
      <td>${r.id}</td>
      <td title="${escapeHtml(r.fish)}">${escapeHtml(r.fish)}</td>
      <td>${escapeHtml(r.telefon || '—')}</td>
      <td title="${escapeHtml(r.murojaat)}">${escapeHtml(r.murojaat)}</td>
      <td>${escapeHtml(r.sana)}</td>
      <td><div class="row-actions">
        <button type="button" class="icon-btn" title="${dict[currentLang].supportIzohLabel}" onclick="openSupportDetail(${r.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg></button>
      </div></td>
    </tr>
  `).join('');
}

function openSupportDetail(id){
  currentSupportDetailId = id;
  openSection('supportDetailView');
  renderSupportDetail();
}

function renderSupportDetail(){
  const box = document.getElementById('supportDetailBox');
  const r = supportData ? supportData.find(x => x.id === currentSupportDetailId) : null;
  if(!box || !r) return;
  const comments = r.comments || [];
  const commentsHtml = comments.length ? comments.map(c => `
    <div class="notif-hist-item" style="cursor:default;">
      <div class="h-meta"><span>${escapeHtml(c.fish)}</span><span>${escapeHtml(c.sana)}</span></div>
      <div class="h-text">${escapeHtml(c.izoh)}</div>
    </div>
  `).join('') : `<p class="loading-row">${dict[currentLang].supportNoComments}</p>`;

  box.innerHTML = `
    <div class="notif-hist-item" style="cursor:default;">
      <div class="h-meta"><span>${escapeHtml(r.fish)} (${escapeHtml(r.login)}) • ${escapeHtml(r.telefon || '—')}</span><span>${escapeHtml(r.sana)}</span></div>
      <div class="h-text">${escapeHtml(r.murojaat)}</div>
    </div>
    <h3 style="font-size:14px; margin:20px 0 10px;">${dict[currentLang].supportCommentsTitle}</h3>
    <div style="display:flex; flex-direction:column; gap:10px; margin-bottom:20px;">${commentsHtml}</div>
    <div class="field" style="margin-bottom:0;">
      <label style="font-size:11px; text-transform:uppercase; color:var(--text-dim); display:block; margin-bottom:5px;">${dict[currentLang].supportIzohLabel}</label>
      <textarea id="newSupportComment" rows="3" placeholder="${dict[currentLang].supportIzohPh}"></textarea>
    </div>
    <div style="display:flex; align-items:center; gap:10px; margin-top:8px;">
      <button type="button" class="back-btn" onclick="saveSupportComment()">${dict[currentLang].supportAddCommentBtn}</button>
      <span id="supportCommentMsg" style="font-size:12px;"></span>
    </div>
  `;
}

async function saveSupportComment(){
  const izoh = document.getElementById('newSupportComment').value.trim();
  const msg = document.getElementById('supportCommentMsg');
  msg.textContent = '';
  if(!izoh) return;
  try{
    const data = await apiCall({ action:'addSupportComment', token: currentUserForKpi.token, id: currentSupportDetailId, izoh });
    if(data.success){
      msg.textContent = dict[currentLang].savedOk; msg.style.color = 'var(--teal)';
      await loadSupportRequests();
      renderSupportDetail();
    } else {
      msg.textContent = dict[currentLang].savedErr; msg.style.color = 'var(--coral)';
    }
  } catch(ex){
    msg.textContent = 'Xatolik: ' + ex.message; msg.style.color = 'var(--coral)';
  }
}

/* ===== Rol guruhlari (backend/src/Roles.php bilan bir xil mantiq) =====
   anticor-admin/anticor/super-admin — "Korrupsiyaga qarshi kurashish";
   anticor-admin/super-admin — "Xodimlar" (ko'rish + qo'shish/o'chirish +
   rolni istalgancha o'zgartirish + ma'lumotlarini tahrirlash);
   anticor-admin/super-admin — Anticor tarkibini boshqarish (test/hujjat/
   so'rovnoma savoli qo'shish-o'chirish, zaxira nusxa — parol hash'lari
   bor bo'lgani uchun bu ham shu (qat'iyroq) darajaga tegishli). */
const ANTICOR_VIEW = ['anticor-admin', 'anticor', 'super-admin'];
const ANTICOR_MANAGE = ['anticor-admin', 'super-admin'];
const HR_VIEW = ['anticor-admin', 'super-admin'];
const HR_MANAGE = ['anticor-admin', 'super-admin'];
const HR_EDIT = ['anticor-admin', 'super-admin'];
const NOTIFY_SEND = ['anticor-admin', 'anticor', 'super-admin'];
// Xaridlar reyestri: anticor-admin/super-admin ko'radi va yozuv kirita oladi.
const PURCHASE_VIEW = ['anticor-admin', 'super-admin'];
const PURCHASE_ENTRY = ['anticor-admin', 'super-admin'];

function init(){
  const user = getCurrentUser();
  if(!user || !user.token){ window.location.href = 'login.html'; return; }
  const rol = user.rol;

  const canAnticor = ANTICOR_VIEW.includes(rol);
  const canAnticorManage = ANTICOR_MANAGE.includes(rol);
  const canEmployees = HR_VIEW.includes(rol);
  const canAddEmployee = HR_MANAGE.includes(rol);
  const canHrDocs = ['anticor-admin', 'super-admin'].includes(rol); // Yuborilgan hujjatlar
  const canManage = canAnticorManage; // Test savollari, Hujjat qo'shish — faqat anticor-admin/super-admin
  const canSurvey = canAnticor; // Anonim so'rovnoma — ichki huquqlar canAnticorManage'ga qarab farqlanadi (openSurveyEntry)
  const canNotifSend = NOTIFY_SEND.includes(rol);
  const canSupport = canAnticor;
  const canReports = canAnticor; // Hisobotlar — faqat ko'rish, shu bois anticor ham kiradi
  const canErrorLog = canAnticorManage; // Tizim jurnali — xom xatolik matnlarini o'z ichiga oladi, shu bois faqat boshqaruv darajasi (backend: ANTICOR_MANAGE)
  const canBackup = canAnticorManage; // Zaxira nusxada barcha xodimlarning parol hash'lari bor — qat'iyroq daraja
  const canPurchaseView = PURCHASE_VIEW.includes(rol);
  const canPurchaseEntry = PURCHASE_ENTRY.includes(rol);
  const canPurchases = canPurchaseView || canPurchaseEntry;

  if(!canAnticor && !canEmployees && !canNotifSend && !canPurchases){
    document.querySelector('.hub-grid').style.display = 'none';
    document.getElementById('noAccessMsg').style.display = 'block';
    return;
  }

  document.getElementById('anticorCard').style.display = canAnticor ? 'block' : 'none';
  document.getElementById('employeesCard').style.display = canEmployees ? 'block' : 'none';
  document.getElementById('hrDocsCard').style.display = canHrDocs ? 'block' : 'none';
  document.getElementById('purchasesCard').style.display = canPurchases ? 'block' : 'none';
  document.getElementById('purchasesRegistryCard').style.display = canPurchaseView ? 'block' : 'none';
  document.getElementById('purchaseEntrySubCard').style.display = canPurchaseEntry ? 'block' : 'none';
  document.getElementById('conflictCard').style.display = canAnticor ? 'block' : 'none';
  document.getElementById('testsCard').style.display = canManage ? 'block' : 'none';
  document.getElementById('docsCard').style.display = canManage ? 'block' : 'none';
  document.getElementById('certCard').style.display = canManage ? 'block' : 'none';
  document.getElementById('surveyCard').style.display = canSurvey ? 'block' : 'none';
  document.getElementById('notifSendCard').style.display = canNotifSend ? 'block' : 'none';
  document.getElementById('addEmployeeSubCard').style.display = canAddEmployee ? 'block' : 'none';
  document.getElementById('supportCard').style.display = canSupport ? 'block' : 'none';
  document.getElementById('reportsCard').style.display = canReports ? 'block' : 'none';
  document.getElementById('backupCard').style.display = canBackup ? 'block' : 'none';
  document.getElementById('errorLogCard').style.display = canErrorLog ? 'block' : 'none';

  document.getElementById('reminderBtn').style.display = canNotifSend ? 'inline-block' : 'none';

  currentUserForKpi = user;
  if(canAnticor) loadStats(user);
  if(canEmployees) loadUsersList(user);
  if(canSupport) loadSupportRequests();
  setTimeout(loadNotifBadge, 400);
}
init();

/* ---------- Modal (main.html bilan bir xil) ---------- */
function openModal(id){ document.getElementById(id).classList.add('show'); }
function closeModal(id){ document.getElementById(id).classList.remove('show'); }

/* ---------- Xabarnomalar ---------- */
let notifCache = null;

function updateNotifBadge(){
  const count = (notifCache || []).filter(n => !n.read).length;
  const badge = document.getElementById('notifBadge');
  if(count > 0){ badge.textContent = count > 99 ? '99+' : String(count); badge.style.display = 'flex'; badge.style.alignItems = 'center'; badge.style.justifyContent = 'center'; }
  else { badge.style.display = 'none'; }
}

async function loadNotifBadge(){
  if(!currentUserForKpi) return;
  try{
    const data = await apiCall({ action:'getMyNotifications', token: currentUserForKpi.token });
    if(data.success){ notifCache = data.notifications; updateNotifBadge(); }
  } catch(ex){ /* jimgina o'tkazib yuboramiz */ }
}

function renderNotifList(){
  const box = document.getElementById('notifList');
  if(!notifCache || notifCache.length === 0){
    box.innerHTML = `<p style="color:var(--text-dim); font-size:13px;">${dict[currentLang].notifEmpty}</p>`;
    return;
  }
  box.innerHTML = notifCache.map(n => `
    <div class="notif-item ${n.read?'':'unread'}" data-id="${n.id}">
      <div class="notif-text">${escapeHtml(n.text)}</div>
    </div>
  `).join('');
}

async function openNotifPanel(){
  openModal('notifModal');
  if(!notifCache){
    document.getElementById('notifList').innerHTML = `<p style="color:var(--text-dim); font-size:13px;">${dict[currentLang].loading}</p>`;
  }
  try{
    const data = await apiCall({ action:'getMyNotifications', token: currentUserForKpi.token });
    if(data.success){
      notifCache = data.notifications;
      renderNotifList();
      const unread = notifCache.filter(n => !n.read);
      for(const n of unread){
        apiCall({ action:'markNotificationRead', token: currentUserForKpi.token, notifId: n.id }).catch(()=>{});
        n.read = true;
      }
      updateNotifBadge();
    }
  } catch(ex){
    document.getElementById('notifList').innerHTML = `<p style="color:var(--coral); font-size:13px;">Xatolik: ${ex.message}</p>`;
  }
}


// XAVFSIZLIK: brauzer "Ortga" tugmasi orqali keshdagi (bfcache) sahifani qaytarganda
// (masalan, Chiqish qilingandan keyin) sessiyani qayta tekshiradi — agar sessiya
// endi mavjud bo'lmasa (chiqilgan bo'lsa), darhol login sahifasiga qaytaradi.
window.addEventListener('pageshow', function(event){
  if (event.persisted) {
    let u;
    try{ u = JSON.parse(sessionStorage.getItem('km_user')); }catch(e){}
    if (!u || !u.token) { window.location.href = 'login.html'; }
  }
});

// XAVFSIZLIK: 10 daqiqa harakatsizlikdan so'ng avtomat chiqish.
// Embedded (iframe) rejimda bu hisob asosiy sahifa (main.html) tomonidan
// yuritiladi — shu yerdagi harakatlar faqat parent'ga xabar qilinadi
// (postMessage), ikkita mustaqil taymer bir-biriga zid ishlamasligi uchun.
(function initIdleLogout(){
  const u = getCurrentUser();
  if(!u || !u.token) return;
  const embedded = window.self !== window.top;
  if(embedded){
    ['mousedown','mousemove','keydown','wheel','touchstart','click'].forEach(evt => {
      window.addEventListener(evt, function(){
        try{ window.parent.postMessage({ type:'km-activity' }, window.location.origin); }catch(e){}
      }, { passive:true });
    });
    return;
  }
  const IDLE_LIMIT_MS = 10 * 60 * 1000;
  let idleTimer = null;
  function doIdleLogout(){
    const cur = getCurrentUser();
    const token = cur && cur.token;
    try{ sessionStorage.removeItem('km_user'); }catch(e){}
    if(token){ fetch(API_BASE_URL, { method:'POST', headers:{ 'Content-Type':'application/json' }, body: JSON.stringify({ action:'logout', token }), credentials:'same-origin' }).catch(()=>{}); }
    window.location.href = 'login.html';
  }
  function resetIdleTimer(){
    if(idleTimer) clearTimeout(idleTimer);
    idleTimer = setTimeout(doIdleLogout, IDLE_LIMIT_MS);
  }
  ['mousedown','mousemove','keydown','wheel','touchstart','click'].forEach(evt => {
    window.addEventListener(evt, resetIdleTimer, { passive:true });
  });
  resetIdleTimer();
})();

// Tema/til boshqa oynada (masalan asosiy sahifa topbar'idan) o'zgartirilsa,
// shu (iframe ichidagi) sahifada ham darhol yangilanadi.
window.addEventListener('storage', function(e){
  if(e.key === 'km_theme'){ theme = e.newValue || 'light'; applyTheme(); }
  if(e.key === 'km_lang'){ currentLang = e.newValue || 'uz'; applyLang(); renderAll(); }
});
