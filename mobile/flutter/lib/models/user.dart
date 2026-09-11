class AppUser {
  final int id;
  final String familiya;
  final String ism;
  final String? otasi;
  final String? tugilganSana;
  final String? lavozim;
  final String? bolinma;
  final String? telefon;
  final String? rasm;
  final String rol;

  AppUser({
    required this.id,
    required this.familiya,
    required this.ism,
    this.otasi,
    this.tugilganSana,
    this.lavozim,
    this.bolinma,
    this.telefon,
    this.rasm,
    required this.rol,
  });

  String get fullName => [familiya, ism, otasi].where((e) => e != null && e!.isNotEmpty).join(' ');

  factory AppUser.fromJson(Map<String, dynamic> json) {
    return AppUser(
      id: json['id'] as int,
      familiya: (json['familiya'] as String?) ?? '',
      ism: (json['ism'] as String?) ?? '',
      otasi: json['otasi'] as String?,
      tugilganSana: json['tugilganSana'] as String?,
      lavozim: json['lavozim'] as String?,
      bolinma: json['bolinma'] as String?,
      telefon: json['telefon'] as String?,
      rasm: json['rasm'] as String?,
      rol: (json['rol'] as String?) ?? 'user',
    );
  }
}
