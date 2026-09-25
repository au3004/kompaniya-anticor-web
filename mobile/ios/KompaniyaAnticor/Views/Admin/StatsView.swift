import SwiftUI

struct StatsView: View {
    @EnvironmentObject var session: SessionStore

    @State private var summary: [String: Any]?
    @State private var employees: [[String: Any]] = []
    @State private var loading = true
    @State private var error: String?

    var body: some View {
        Group {
            if loading {
                ProgressView()
            } else if let error {
                Text(error).foregroundColor(AppColors.coral)
            } else {
                List {
                    if let summary {
                        Section {
                            HStack(spacing: 8) {
                                statTile("Jami", summary.int("total") ?? 0, AppColors.azure)
                                statTile("Hujjat", summary.int("docsDone") ?? 0, AppColors.teal)
                            }
                            HStack(spacing: 8) {
                                statTile("O'tdi", summary.int("testsPassed") ?? 0, AppColors.teal)
                                statTile("Yiqildi", summary.int("testsFailed") ?? 0, AppColors.coral)
                            }
                        }
                        .listRowInsets(EdgeInsets())
                        .listRowBackground(Color.clear)
                    }
                    Section("Xodimlar") {
                        ForEach(Array(employees.enumerated()), id: \.offset) { _, e in
                            VStack(alignment: .leading, spacing: 3) {
                                Text(e.str("fish") ?? "").font(.subheadline.weight(.semibold))
                                Text("\(e.str("lavozim") ?? "—") · \(e.str("bolinma") ?? "—")").font(.caption).foregroundColor(AppColors.textDim)
                                HStack(spacing: 10) {
                                    Label("Hujjat", systemImage: "book")
                                        .font(.caption2)
                                        .foregroundColor(e["hujjatSana"] != nil ? AppColors.teal : AppColors.textDim)
                                    Label("Test", systemImage: "checkmark.seal")
                                        .font(.caption2)
                                        .foregroundColor((e["testTaken"] as? Bool) == true ? ((e["passed"] as? Bool) == true ? AppColors.teal : AppColors.coral) : AppColors.textDim)
                                }
                            }
                        }
                    }
                }
                .listStyle(.insetGrouped)
                .refreshable { await load() }
            }
        }
        .navigationTitle("Statistika")
        .navigationBarTitleDisplayMode(.inline)
        .task { await load() }
    }

    private func statTile(_ label: String, _ value: Int, _ color: Color) -> some View {
        VStack(alignment: .leading, spacing: 2) {
            Text("\(value)").font(.title2.weight(.heavy)).foregroundColor(color)
            Text(label).font(.caption).foregroundColor(AppColors.textDim)
        }
        .padding(14)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .cornerRadius(12)
    }

    private func load() async {
        loading = true
        error = nil
        do {
            let data = try await session.api.call("getStats")
            summary = data["summary"] as? [String: Any]
            employees = data.arr("employees")
        } catch let apiError as APIError {
            error = apiError.message
        } catch {
            error = "Xatolik"
        }
        loading = false
    }
}
