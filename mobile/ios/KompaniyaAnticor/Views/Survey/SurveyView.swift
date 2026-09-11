import SwiftUI

struct SurveyView: View {
    @EnvironmentObject var session: SessionStore

    @State private var loading = true
    @State private var active = true
    @State private var error: String?
    @State private var questions: [[String: Any]] = []
    @State private var choiceAnswers: [String: String] = [:]
    @State private var starAnswers: [String: Int] = [:]
    @State private var textAnswers: [String: String] = [:]
    @State private var submitting = false
    @State private var done = false

    var body: some View {
        Group {
            if loading {
                ProgressView()
            } else if let error {
                Text(error).foregroundColor(AppColors.coral)
            } else if !active {
                Text("So'rovnoma hozircha faol emas").foregroundColor(AppColors.textDim)
            } else if done {
                VStack(spacing: 12) {
                    Image(systemName: "checkmark.circle").font(.system(size: 56)).foregroundColor(AppColors.teal)
                    Text("Rahmat! Javoblaringiz qabul qilindi.").font(.headline).multilineTextAlignment(.center)
                }
                .frame(maxWidth: .infinity, maxHeight: .infinity)
            } else {
                formView
            }
        }
        .navigationTitle("So'rovnoma")
        .navigationBarTitleDisplayMode(.inline)
        .task { await load() }
    }

    private var formView: some View {
        VStack(spacing: 0) {
            List {
                ForEach(Array(questions.enumerated()), id: \.offset) { i, q in
                    let id = "\(q.int("id") ?? 0)"
                    let turi = q.str("turi") ?? "tanlov"
                    let uz = (q["uz"] as? [String: Any]) ?? [:]
                    VStack(alignment: .leading, spacing: 8) {
                        Text("\(i + 1). \(uz.str("savol") ?? "")").font(.subheadline.weight(.semibold))
                        if turi == "tanlov" {
                            ForEach(["a", "b", "c", "d"], id: \.self) { letter in
                                if let text = uz.str(letter), !text.isEmpty {
                                    Button {
                                        choiceAnswers[id] = letter.uppercased()
                                    } label: {
                                        HStack {
                                            Image(systemName: choiceAnswers[id] == letter.uppercased() ? "largecircle.fill.circle" : "circle")
                                            Text(text)
                                        }
                                    }
                                    .foregroundColor(AppColors.text)
                                }
                            }
                        } else if turi == "yulduz" {
                            let stars = q.int("stars") ?? 5
                            HStack {
                                ForEach(1...max(stars, 1), id: \.self) { v in
                                    Button {
                                        starAnswers[id] = v
                                    } label: {
                                        Image(systemName: (starAnswers[id] ?? 0) >= v ? "star.fill" : "star")
                                            .foregroundColor(AppColors.coral)
                                    }
                                }
                            }
                        } else {
                            TextField("Javobingizni yozing...", text: Binding(
                                get: { textAnswers[id] ?? "" },
                                set: { textAnswers[id] = $0 }
                            ), axis: .vertical)
                            .textFieldStyle(.roundedBorder)
                            .lineLimit(3, reservesSpace: true)
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
                    Text("Yuborish").frame(maxWidth: .infinity)
                }
            }
            .buttonStyle(.borderedProminent).tint(AppColors.azure)
            .disabled(submitting)
            .padding()
        }
    }

    private func load() async {
        loading = true
        error = nil
        do {
            let data = try await session.api.call("getSurveyQuestions")
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
            var payload: [[String: String]] = []
            for q in questions {
                let id = "\(q.int("id") ?? 0)"
                let turi = q.str("turi") ?? "tanlov"
                var value: String?
                if turi == "tanlov" { value = choiceAnswers[id] }
                else if turi == "yulduz" { value = starAnswers[id].map { "\($0)" } }
                else { value = textAnswers[id] }
                if let value, !value.isEmpty {
                    payload.append(["id": id, "letter": value])
                }
            }
            do {
                _ = try await session.api.call("submitSurveyAnswers", ["answers": payload])
                done = true
            } catch {
                // xatolik bo'lsa jim qoldiramiz
            }
            submitting = false
        }
    }
}
