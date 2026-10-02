/// backend/src/Roles.php bilan bir xil rol/imtiyoz guruhlari.
///
/// Ilgari alohida bo'lgan hr-admin/hr/rahbariyat/xarid rollari olib
/// tashlangan — ularning barcha vakolati anticor-adminga o'tkazilgan.
class Roles {
  static const user = 'user';
  static const anticorAdmin = 'anticor-admin';
  static const anticor = 'anticor';
  static const superAdmin = 'super-admin';

  static const anticorView = [anticorAdmin, anticor, superAdmin];
  static const anticorManage = [anticorAdmin, superAdmin];
  static const hrView = [anticorAdmin, superAdmin];
  static const hrManage = [anticorAdmin, superAdmin];
  static const hrEdit = [anticorAdmin, superAdmin];
  static const notifySend = [anticorAdmin, anticor, superAdmin];
  static const anyPanelAccess = [anticorAdmin, anticor, superAdmin];
  static const purchaseView = [anticorAdmin, superAdmin];
  static const purchaseEntry = [anticorAdmin, superAdmin];
  static const hrDocs = [anticorAdmin, superAdmin];
}
