import SwiftUI

struct TestView: View {
    @EnvironmentObject var session: SessionStore

    @State private var loading = true
    @State private var active = true
    @State private var error: String?
    @State private var questions: [[String: Any]] = []
    @State private var answers: [String: String] = [:]
    @State private var submitting = false
    @State private var result: [String: Any]?

    var body: some View {
        Group {
            if loading {
                ProgressView()
            } else if let error {
                Text(error).foregroundColor(AppColors.coral)
            } else if !active {
                Text("Test hozircha faol emas").foregroundColor(AppColors.textDim)
            } else if let result {
                resultView(result)
            } else {
                quizView
            }
        }
        .navigationTitle("Test")
        .navigationBarTitleDisplayMode(.inline)
        .task { await load() }
    }

    private func resultView(_ result: [String: Any]) -> some View {
        let passed = (result["passed"] as? Bool) == true
        return VStack(spacing: 12) {
            Image(systemName: passed ? "checkmark.circle" : "xmark.circle")
                .font(.system(size: 56))
                .foregroundColor(passed ? AppColors.teal : AppColors.coral)
            Text(passed ? "Muvaffaqiyatli topshirildi" : "Muvaffaqiyatsiz").font(.headline)
            Text("\(result.int("percent") ?? 0)% (\(result.int("points") ?? 0)/\(result.int("maxPoints") ?? 0))")
                .font(.title.weight(.heavy))
                .foregroundColor(AppColors.azure)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
    }

    private var quizView: some View {
        let allAnswered = answers.count == questions.count && !questions.isEmpty
        return VStack(spacing: 0) {
            List {
                ForEach(Array(questions.enumerated()), id: \.offset) { i, q in
                    let id = "\(q.int("id") ?? 0)"
                    let uz = (q["uz"] as? [String: Any]) ?? [:]
                    VStack(alignment: .leading, spacing: 8) {
                        Text("\(i + 1). \(uz.str("savol") ?? "")").font(.subheadline.weight(.semibold))
                        ForEach(["a", "b", "c", "d"], id: \.self) { letter in
                            if let text = uz.str(letter), !text.isEmpty {
                                Button {
                                    answers[id] = letter.uppercased()
                                } label: {
                                    HStack {
                                        Image(systemName: answers[id] == letter.uppercased() ? "largecircle.fill.circle" : "circle")
                                        Text(text)
                                    }
                                }
                                .foregroundColor(AppColors.text)
                            }
                        }
                    }
                    .padding(.vertical, 4)
                }
            }
            .listStyle(.plain)

            Button {
                submit()
            } label: {
                if submitting {
                    ProgressView().tint(.white).frame(maxWidth: .infinity)
                } else {
                    Text(allAnswered ? "Yakunlash" : "Barcha savollarga javob bering (\(answers.count)/\(questions.count))")
                        .frame(maxWidth: .infinity)
                }
            }
            .buttonStyle(.borderedProminent).tint(AppColors.azure)
            .disabled(!allAnswered || submitting)
            .padding()
        }
    }

    private func load() async {
        loading = true
        error = nil
        do {
            let data = try await session.api.call("getTestQuestions")
            active = (data["active"] as? Bool) != false
            questions = data.arr("questions")
        } catch let apiError as APIError {
            error = apiError.message
        } catch {
            error = "Xatolik"
        }
        loading = false
    }

    private func submit() {
        submitting = true
        Task {
            let payload = answers.map { ["id": $0.key, "letter": $0.value] }
            do {
                result = try await session.api.call("submitTest", ["answers": payload])
            } catch {
                // xatolik bo'lsa jim qoldiramiz, quiz qayta ko'rinadi
            }
            submitting = false
        }
    }
}
