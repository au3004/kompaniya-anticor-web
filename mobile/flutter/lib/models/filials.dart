/// backend/src/Filials.php bilan bir xil belgilangan ro'yxat (erkin matn
/// emas — tanlov orqali belgilanadi).
class Filials {
  static const ijroiyaApparati = 'ijroiya_apparati';
  static const markaziy = 'markaziy';
  static const shimoliy = 'shimoliy';
  static const sharqiy = 'sharqiy';
  static const janubiy = 'janubiy';
  static const garbiy = 'garbiy';
  static const janubiGarbiy = 'janubi_garbiy';
  static const texnik = 'texnik';
  static const tmsHub = 'tms_hub';

  static const all = [
    ijroiyaApparati,
    markaziy,
    shimoliy,
    sharqiy,
    janubiy,
    garbiy,
    janubiGarbiy,
    texnik,
    tmsHub,
  ];

  static const labels = {
    ijroiyaApparati: 'Ijroiya apparati',
    markaziy: 'Markaziy filial',
    shimoliy: 'Shimoliy filial',
    sharqiy: 'Sharqiy filial',
    janubiy: 'Janubiy filial',
    garbiy: "G'arbiy filial",
    janubiGarbiy: "Janubi-G'arbiy filial",
    texnik: 'Ixtisoslashtirilgan texnik filial',
    tmsHub: '"TMS Hub" filiali',
  };

  static String label(String? f) => (f == null || f.isEmpty) ? '—' : (labels[f] ?? f);
}
