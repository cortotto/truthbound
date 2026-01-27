<?php
// Check for the calibration cookie before rendering any HTML
if (isset($_COOKIE['is_calibrated']) && $_COOKIE['is_calibrated'] === 'TRUE') {
    header("Location: theChamber.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certification | TruthBound</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .glass { background: rgba(15, 23, 42, 0.9); backdrop-filter: blur(16px); }
        .fade-in { animation: fadeIn 0.5s ease-out forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .cookie-blur { backdrop-filter: blur(8px); }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-6">

    <div id="cookie-overlay" class="fixed inset-0 z-[100] flex items-center justify-center p-6 bg-slate-950/60 cookie-blur hidden">
        <div class="max-w-md w-full glass border border-slate-800 rounded-[2.5rem] p-8 text-center shadow-2xl">
            <div class="text-5xl mb-6">🍪</div>
            <h2 class="text-2xl font-black mb-4">Cognitive Cookies</h2>
            <p class="text-slate-400 text-sm leading-relaxed mb-8">
                To track your calibration status and grant access to the Chamber, we must store a small token on your device.
            </p>
            <button onclick="acceptCookies()" class="w-full py-4 bg-blue-600 hover:bg-blue-500 text-white font-black rounded-2xl uppercase tracking-widest text-xs transition-all">
                Allow Data Storage
            </button>
        </div>
    </div>

    <div id="quiz-container" class="max-w-xl w-full glass border border-slate-800 rounded-3xl p-8 md:p-12 shadow-2xl relative overflow-hidden">
        
        <div class="absolute top-0 left-0 w-full h-1 bg-slate-800">
            <div id="progress-fill" class="h-full bg-blue-500 transition-all duration-500" style="width: 33%"></div>
        </div>

        <div id="quiz-content" class="fade-in">
            </div>

        <div id="feedback-area" class="hidden mt-8 fade-in">
            <div id="feedback-card" class="p-6 rounded-2xl border mb-6"></div>
            <button id="next-btn" class="w-full py-4 bg-white text-slate-950 font-black rounded-xl uppercase tracking-widest text-sm hover:bg-slate-200 transition-all">
                Proceed to Next Step
            </button>
        </div>
    </div>

    <script>
        // --- COOKIE LOGIC ---
        
        function getCookie(name) {
            let value = "; " + document.cookie;
            let parts = value.split("; " + name + "=");
            if (parts.length === 2) return parts.pop().split(";").shift();
        }

        function setCalibrationCookie(status) {
            // Cookie expires in 30 days
            const d = new Date();
            d.setTime(d.getTime() + (30*24*60*60*1000));
            document.cookie = `is_calibrated=${status}; expires=${d.toUTCString()}; path=/`;
        }

        function checkInitialAccess() {
            const cookie = getCookie('is_calibrated');
            if (!cookie) {
                document.getElementById('cookie-overlay').classList.remove('hidden');
            } else if (cookie === 'TRUE') {
                window.location.href = 'theChamber.php';
            } else {
                // User is FALSE (started but didn't finish), start quiz
                loadQuestion();
            }
        }

        function acceptCookies() {
            setCalibrationCookie('FALSE');
            document.getElementById('cookie-overlay').classList.add('hidden');
            loadQuestion();
        }

        // --- QUIZ LOGIC ---

        const quizData = [
            {
                statement: "The freezing point of water at sea level is 0°C.",
                options: [
                    { label: "Objective", correct: true, explain: "Correct. This is a physical property of the universe that remains true regardless of human culture or opinion." },
                    { label: "Intersubjective", correct: false, explain: "Not quite. While 'Celsius' is a human-scale, the physical state change is an independent reality." },
                    { label: "Subjective", correct: false, explain: "Incorrect. Temperature is a measurable value, not a personal feeling." }
                ]
            },
            {
                statement: "A $100 bill has more value than a $1 bill.",
                options: [
                    { label: "Objective", correct: false, explain: "Incorrect. Physically, they are both just paper. Their 'value' is not a physical property." },
                    { label: "Intersubjective", correct: true, explain: "Correct. This is a shared social fiction. It only works because we collectively agree to treat them differently." },
                    { label: "Subjective", correct: false, explain: "No. If you personally decide it's worth $1000, no one will accept it. It requires group agreement." }
                ]
            },
            {
                statement: "The Mona Lisa is the most beautiful painting in history.",
                options: [
                    { label: "Objective", correct: false, explain: "Incorrect. 'Beauty' cannot be measured by a scientific instrument." },
                    { label: "Intersubjective", correct: false, explain: "Mistaken. Even if everyone agrees, beauty originates from individual internal perception." },
                    { label: "Subjective", correct: true, explain: "Correct. Aesthetic appreciation is a purely internal, individual experience." }
                ]
            }
        ];

        let currentStep = 0;

        function loadQuestion() {
            const data = quizData[currentStep];
            const feedbackArea = document.getElementById('feedback-area');
            const progressFill = document.getElementById('progress-fill');
            
            feedbackArea.classList.add('hidden');
            progressFill.style.width = ((currentStep + 1) / quizData.length) * 100 + "%";

            let html = `
                <div class="text-center mb-8">
                    <span class="text-xs font-bold text-blue-500 uppercase tracking-widest">Step ${currentStep + 1} of 3</span>
                    <h2 class="text-3xl font-bold mt-4 leading-tight">"${data.statement}"</h2>
                </div>
                <div class="grid gap-4">
                    ${data.options.map((opt, index) => `
                        <button onclick="handleVote(${index})" class="option-btn w-full p-5 bg-slate-900 border border-slate-700 rounded-2xl text-left hover:border-slate-500 transition-all font-bold">
                            ${opt.label}
                        </button>
                    `).join('')}
                </div>
            `;
            document.getElementById('quiz-content').innerHTML = html;
        }

        window.handleVote = function(index) {
            const choice = quizData[currentStep].options[index];
            const feedbackArea = document.getElementById('feedback-area');
            const feedbackCard = document.getElementById('feedback-card');
            const nextBtn = document.getElementById('next-btn');

            feedbackArea.classList.remove('hidden');
            feedbackCard.innerHTML = `<p class="text-sm leading-relaxed">${choice.explain}</p>`;

            if (choice.correct) {
                feedbackCard.className = "p-6 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-100";
                nextBtn.classList.remove('hidden');
                document.querySelectorAll('.option-btn').forEach(btn => btn.disabled = true);
            } else {
                feedbackCard.className = "p-6 rounded-2xl border border-red-500/30 bg-red-500/10 text-red-100";
                nextBtn.classList.add('hidden');
            }
        };

        document.getElementById('next-btn').addEventListener('click', () => {
            currentStep++;
            if (currentStep < quizData.length) {
                loadQuestion();
            } else {
                finalizeCalibration();
            }
        });

        function finalizeCalibration() {
            setCalibrationCookie('TRUE');
            document.getElementById('progress-fill').style.width = "100%";
            document.getElementById('quiz-content').innerHTML = `
                <div class="text-center py-6">
                    <div class="w-20 h-20 bg-emerald-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-[0_0_30px_rgba(16,185,129,0.4)]">
                        <svg class="w-10 h-10 text-slate-950" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <h1 class="text-4xl font-black mb-2">CERTIFIED</h1>
                    <p class="text-slate-400 mb-8">Calibration complete. Your lens is clear.</p>
                    <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl mb-8">
                        <p class="text-xs uppercase tracking-widest text-slate-500 mb-2">Cognitive Score</p>
                        <p class="text-5xl font-mono font-bold text-emerald-400">100%</p>
                    </div>
                    <button onclick="window.location.href='theChamber.php'" class="w-full py-4 bg-blue-600 hover:bg-blue-500 text-white font-black rounded-xl uppercase tracking-widest text-sm transition-all shadow-lg">
                        Enter the Global Feed
                    </button>
                </div>
            `;
            document.getElementById('feedback-area').classList.add('hidden');
        }

        // Initialize on load
        checkInitialAccess();
    </script>
</body>
</html>