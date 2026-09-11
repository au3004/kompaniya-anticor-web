/// Veb versiyaning (main.html) relativeTypes ro'yxati bilan bir xil.
class RelativeType {
  final String key;
  final String label;
  const RelativeType(this.key, this.label);
}

const List<RelativeType> kRelativeTypes = [
  RelativeType('aka-uka', 'Aka-uka'),
  RelativeType('opa-singil', 'Opa-singil'),
  RelativeType('ogil-qiz', "O'g'il-qiz"),
  RelativeType('turmush-ortogi', "Turmush o'rtog'i"),
  RelativeType('qaynota-ona', 'Qaynota/qaynona'),
  RelativeType('qayn-akauka', 'Qayn aka-uka'),
  RelativeType('qayn-opasingil', 'Qayn opa-singil'),
];

const Map<String, String> kHolatLabels = {
  'info': "To'ldirildi",
  'no_info': "Ma'lumotga ega emas",
  'deceased': 'Vafot etgan',
  'no_contact': 'Ajrashgan / aloqada emas',
};

/// Bitta yaqin qarindosh qatori — ota/ona (fixed:true, o'chirilmaydi) yoki
/// foydalanuvchi qo'shgan tur (typeKey + bir xil turdan bir nechtasi
/// bo'lsa suffixNum: 2, 3, ...).
class RelativeEntry {
  final String id;
  final String? typeKey;
  final int? suffixNum;
  final bool fixed;
  RelativeData? data;

  RelativeEntry({required this.id, this.typeKey, this.suffixNum, this.fixed = false, this.data});

  bool get filled => data != null && (data!.holat?.isNotEmpty ?? false);

  String label() {
    if (id == 'ota') return 'Ota';
    if (id == 'ona') return 'Ona';
    final type = kRelativeTypes.where((t) => t.key == typeKey).firstOrNull;
    final base = type?.label ?? '';
    return suffixNum != null ? '$base $suffixNum' : base;
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'typeKey': typeKey,
        'suffixNum': suffixNum,
        'fixed': fixed,
        'filled': filled,
        'label': label(),
        'data': data?.toJson(),
      };
}

extension _FirstOrNull<T> on Iterable<T> {
  T? get firstOrNull => isEmpty ? null : first;
}

/// Bitta qarindosh haqidagi to'ldiriladigan ma'lumot (declModalFieldsHtml
/// bilan bir xil maydonlar).
class RelativeData {
  String? holat; // '' | info | no_info | deceased | no_contact
  String? fullName;
  String? jshshir;
  String? address;
  String? workType; // entrepreneur | other
  String? workplacePosition;
  String? legalEntityName;
  String? stir;
  String? ownershipShare;
  String? managementRole;

  RelativeData({
    this.holat,
    this.fullName,
    this.jshshir,
    this.address,
    this.workType,
    this.workplacePosition,
    this.legalEntityName,
    this.stir,
    this.ownershipShare,
    this.managementRole,
  });

  RelativeData copy() => RelativeData(
        holat: holat,
        fullName: fullName,
        jshshir: jshshir,
        address: address,
        workType: workType,
        workplacePosition: workplacePosition,
        legalEntityName: legalEntityName,
        stir: stir,
        ownershipShare: ownershipShare,
        managementRole: managementRole,
      );

  String workplaceText() {
    if (holat != 'info') return '—';
    if (workType == 'other') return (workplacePosition?.isNotEmpty == true) ? workplacePosition! : '—';
    if (workType == 'entrepreneur') return (legalEntityName?.isNotEmpty == true) ? legalEntityName! : '—';
    return '—';
  }

  Map<String, dynamic> toJson() => {
        'holat': holat,
        'fullName': fullName,
        'jshshir': jshshir,
        'address': address,
        'workType': workType,
        'workplacePosition': workplacePosition,
        'legalEntityName': legalEntityName,
        'stir': stir,
        'ownershipShare': ownershipShare,
        'managementRole': managementRole,
      };
}

/// Veb versiyadagi ADMIN_DECL_QUESTIONS bilan bir xil 7 ta savol — birinchi
/// 5 tasi HA/YO'Q + izoh, oxirgi 2 tasi faqat erkin matn (izoh).
const List<String> kDeclarationQuestions = [
  "Siz boshqaruv organi (boshqaruv, Kuzatuv kengashi, direktorlar kengashi va hokazolar) xodimi, a'zosi, qandaydir tashkilot direktori (bosh buxgalteri, buxgalteri va hokazo) yoki vakilimisiz?",
  "Sizda / yaqin qarindoshlaringizda qandaydir tashkilotlarda moliyaviy manfaatdorlik bormi (ustav kapitalida ishtirok, aksiya va obligatsiyalarga egalik) yoki bunday tashkilotlar qarorlariga boshqa tarzda ta'sir ko'rsata olasizmi?",
  "Yaqin qarindoshlaringiz boshqaruv organlari (boshqaruv, kuzatuv kengashi, direktorlar kengashi va h.k.) xodimi, a'zosi, tashkilot direktori yoki vakilimi?",
  "Yaqin qarindoshlaringiz davlat organlarining mansabdor shaxsi hisoblanadimi?",
  "Shaxsiy manfaatlaringiz, yaqin qarindoshlaringiz yoki aloqador shaxslar manfaatlari yo'lida maxfiy hisoblangan, davlat organlari va tashkilotlarida ishlash davomida ma'lum bo'lgan axborotdan foydalanganmisiz?",
  "Manfaatlar to'qnashuviga olib kelishi mumkin bo'lgan boshqa shart-sharoitlar mavjud bo'lsa, ularni ko'rsatib o'ting.",
  "Zarur topsangiz, har qanday qo'shimcha ma'lumotni ko'rsating.",
];

class DeclarationAnswer {
  String? choice; // 'ha' | "yoq" — faqat birinchi 5 savol uchun
  String note;
  DeclarationAnswer({this.choice, this.note = ''});

  Map<String, dynamic> toJson() => {'choice': choice, 'note': note};
}

/// Veb versiyadagi terms[] (step3 — asosiy atamalar) bilan bir xil.
const List<Map<String, String>> kDeclarationTerms = [
  {
    't': 'Yaqin qarindoshlar',
    'd': "Ota-onalar, aka-ukalar, opa-singillar, o'g'illar, qizlar, er-xotinlar, shuningdek er-xotinlarning ota-onalari, aka-ukalari, opa-singillari va farzandlari.",
  },
  {
    't': 'Aloqador shaxslar',
    'd': "Xodimning yaqin qarindoshlari; xodim va (yoki) yaqin qarindoshlari ustav fondida ulush/aksiyaga ega bo'lgan yuridik shaxs; xodim yoxud yaqin qarindoshlari boshqaruv organi rahbari yoki a'zosi bo'lgan yuridik shaxs.",
  },
  {
    't': "Manfaatlar to'qnashuvi",
    'd': "Xodimning shaxsiy (bevosita yoki bilvosita) manfaatdorligi lavozim majburiyatlarini lozim darajada bajarishiga ta'sir ko'rsatayotgan yoki ko'rsatishi mumkin bo'lgan, shaxsiy manfaatdorlik bilan fuqarolar, tashkilotlar, jamiyat yoki davlat huquqlari va qonuniy manfaatlari o'rtasida qarama-qarshilik yuzaga kelgan (mavjud) yoki kelishi mumkin bo'lgan (ehtimoliy) vaziyat.",
  },
  {
    't': "Xodimning shaxsiy manfaatdorligi",
    'd': "Xodim yoxud u bilan aloqador shaxslar xodim tomonidan qaror qabul qilinishi yoki jarayonda boshqacha ishtirok etishi natijasida olishi mumkin bo'lgan har qanday naf yoki afzallik.",
  },
];
