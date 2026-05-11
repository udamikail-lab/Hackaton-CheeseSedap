<?php session_start(); ?>
<!doctype html>
<html lang="en" class="h-full">
<head>
<title>Register</title>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com/3.4.17"></script>
<script src="https://cdn.jsdelivr.net/npm/lucide@0.263.0/dist/umd/lucide.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
* { font-family: 'DM Sans', sans-serif; }
body { box-sizing: border-box; }
.feature-btn { transition: transform 0.15s, box-shadow 0.15s; }
.feature-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
.feature-btn:active { transform: translateY(0); }
</style>

</head>

<body class="h-full bg-slate-950">

<div class="min-h-full w-full bg-slate-950 overflow-auto">
    <div class="max-w-md mx-auto px-5 py-10">

        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6">

            <a href="/gxbank_html_by_page/index.php" class="inline-flex items-center gap-2 text-emerald-400 hover:text-emerald-300 mb-6">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
                <span class="text-sm font-medium">Back to Login</span>
            </a>

            <h1 class="text-2xl font-bold text-white mb-2">Register</h1>
            <p class="text-slate-400 text-sm mb-6">Create account baru untuk test app ni.</p>

            <?php if(isset($_SESSION['error'])) { ?>
                <div class="bg-red-500/15 text-red-300 rounded-xl p-3 text-sm mb-4">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php } ?>

            <form action="/gxbank_html_by_page/php/register_process.php" method="POST" class="space-y-4">

                <input type="text" name="username" placeholder="Username" required
                    class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-emerald-400">

                <input type="text" name="full_name" id="full_name" placeholder="FULL NAME" required
                    oninput="this.value = this.value.toUpperCase()"
                    style="text-transform: uppercase;"
                    class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-emerald-400">

                <input type="number" name="age" placeholder="Age"
                    class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-emerald-400">

                <input type="email" name="email" placeholder="Email" required
                    class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-emerald-400">

                <input type="password" name="password" placeholder="Password" required
                    class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-emerald-400">

                <button type="submit" class="w-full bg-emerald-500 hover:bg-emerald-600 text-white font-semibold py-3 rounded-xl transition">
                    Register
                </button>

            </form>

            <p class="text-slate-400 text-sm mt-6 text-center">Already have an account?</p>

            <form action="/gxbank_html_by_page/index.php" method="GET">
                <button type="submit"
                    class="w-full text-center bg-slate-800 hover:bg-slate-700 text-white font-semibold py-3 rounded-xl mt-3 transition">
                    Back to Login
                </button>
            </form>

        </div>

    </div>
</div>

<script>lucide.createIcons();</script>

</body>
</html>