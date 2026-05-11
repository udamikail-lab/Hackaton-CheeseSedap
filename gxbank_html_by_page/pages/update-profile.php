<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /gxbank_html_by_page/index.php");
    exit();
}

include "../php/dbconn.php";

$user_id = $_SESSION['user_id'];
$error = "";

$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$user) {
    die("User not found.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST['full_name'] ?? '');
    $age = intval($_POST['age'] ?? 0);
    $email = trim($_POST['email'] ?? '');

    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($full_name == "" || $email == "") {
        $error = "Full name and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } elseif (($new_password != "" || $confirm_password != "") && $current_password == "") {
        $error = "Please enter your current password before changing password.";
    } elseif ($new_password != "" && $new_password != $confirm_password) {
        $error = "New password and confirm password do not match.";
    } elseif ($new_password != "" && !password_verify($current_password, $user['password'])) {
        $error = "Current password is incorrect.";
    } else {
        $check = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ? AND user_id != ? LIMIT 1");
        mysqli_stmt_bind_param($check, "si", $email, $user_id);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            $error = "Email already used by another account.";
        } else {
            if ($new_password != "") {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                $stmt = mysqli_prepare($conn, "UPDATE users SET full_name = ?, age = ?, email = ?, password = ? WHERE user_id = ?");
                mysqli_stmt_bind_param($stmt, "sissi", $full_name, $age, $email, $hashed_password, $user_id);
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE users SET full_name = ?, age = ?, email = ? WHERE user_id = ?");
                mysqli_stmt_bind_param($stmt, "sisi", $full_name, $age, $email, $user_id);
            }

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['full_name'] = $full_name;
                $_SESSION['success'] = "Profile updated successfully.";
                header("Location: /gxbank_html_by_page/dashboard.php");
                exit();
            } else {
                $error = "Failed to update profile: " . mysqli_error($conn);
            }
        }
    }
}
?>

<!doctype html>
<html lang="en" class="h-full">
<head>
<title>Update Information</title>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com/3.4.17"></script>
<script src="https://cdn.jsdelivr.net/npm/lucide@0.263.0/dist/umd/lucide.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
* { font-family: 'DM Sans', sans-serif; }
body { box-sizing: border-box; }
</style>
</head>

<body class="h-full bg-slate-950">
<div class="min-h-full w-full bg-slate-950 overflow-auto">
    <div class="max-w-md mx-auto px-5 py-8">

        <a href="/gxbank_html_by_page/dashboard.php" class="flex items-center gap-2 text-emerald-400 hover:text-emerald-300 mb-6 transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
            <span class="text-sm font-medium">Back to Dashboard</span>
        </a>

        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6">

            <div class="w-12 h-12 rounded-xl bg-emerald-500/15 flex items-center justify-center mb-5">
                <i data-lucide="user-cog" class="w-6 h-6 text-emerald-400"></i>
            </div>

            <h1 class="text-2xl font-bold text-white mb-2">Update Information</h1>
            <p class="text-slate-400 text-sm mb-6">Update your profile details here.</p>

            <?php if($error != "") { ?>
                <div class="bg-red-500/15 text-red-300 rounded-xl p-3 text-sm mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php } ?>

            <form method="POST" class="space-y-4">

                <div>
                    <label class="text-slate-300 text-sm font-medium">Username</label>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required
                        oninput="this.value = this.value.toUpperCase()"
                        style="text-transform: uppercase;"
                        class="w-full mt-2 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-emerald-400">
                </div>

                <div>
                    <label class="text-slate-300 text-sm font-medium">Full Name</label>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required
                        class="w-full mt-2 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-emerald-400">
                </div>

                <div>
                    <label class="text-slate-300 text-sm font-medium">Age</label>
                    <input type="number" name="age" value="<?php echo htmlspecialchars($user['age']); ?>"
                        class="w-full mt-2 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-emerald-400">
                </div>

                <div>
                    <label class="text-slate-300 text-sm font-medium">Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required
                        class="w-full mt-2 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-emerald-400">
                </div>

                <div class="border-t border-slate-800 pt-4">
                    <p class="text-slate-300 text-sm font-medium mb-1">Change Password</p>
                    <p class="text-slate-500 text-xs mb-3">
                        Enter your current password only if you want to change password.
                    </p>

                    <div class="mb-3">
                        <label class="text-slate-400 text-xs">Current Password</label>
                        <input type="password" name="current_password" placeholder="Enter current password"
                            class="w-full mt-2 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-emerald-400">
                    </div>

                    <div class="mb-3">
                        <label class="text-slate-400 text-xs">New Password</label>
                        <input type="password" name="new_password" placeholder="Enter new password"
                            class="w-full mt-2 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-emerald-400">
                    </div>

                    <div>
                        <label class="text-slate-400 text-xs">Confirm New Password</label>
                        <input type="password" name="confirm_password" placeholder="Confirm new password"
                            class="w-full mt-2 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-emerald-400">
                    </div>
                </div>

                <button type="submit"
                    class="w-full bg-emerald-500 hover:bg-emerald-600 text-white font-semibold py-3 rounded-xl transition">
                    Save Changes
                </button>

            </form>

        </div>

    </div>
</div>

<script>lucide.createIcons();</script>
</body>
</html>