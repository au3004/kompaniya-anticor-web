/// backend/src/Roles.php bilan bir xil rol/imtiyoz guruhlari.
class Roles {
  static const user = 'user';
  static const anticorAdmin = 'anticor-admin';
  static const anticor = 'anticor';
  static const hrAdmin = 'hr-admin';
  static const hr = 'hr';
  static const superAdmin = 'super-admin';
  static const rahbariyat = 'rahbariyat';
  static const xarid = 'xarid';

  static const anticorView = [anticorAdmin, anticor, superAdmin];
  static const anticorManage = [anticorAdmin, superAdmin];
  static const hrView = [hrAdmin, hr, rahbariyat, superAdmin];
  static const hrManage = [hrAdmin, superAdmin];
  static const hrEdit = [hrAdmin, hr, superAdmin];
  static const notifySend = [anticorAdmin, anticor, hrAdmin, hr, rahbariyat, superAdmin];
  static const anyPanelAccess = [anticorAdmin, anticor, hrAdmin, hr, rahbariyat, superAdmin, xarid];
  static const purchaseView = [xarid, anticorAdmin, superAdmin];
  static const purchaseEntry = [xarid, superAdmin];
  static const hrDocs = [hrAdmin, hr, superAdmin];
  static const requestApprove = [anticorAdmin, rahbariyat, superAdmin];
}
