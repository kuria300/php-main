<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/login.css">
    <!--Booststrap links-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <!--Boostraplinks-->
    <!--font awesome cdn-->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!--font awesome cdn-->
    <title>login</title>
    <link rel="icon" href="logo2.png">
</head>
<body>
<section class="loginBody">
     <div class="imageBox">
        <img src="login-logo.png" alt="Fee management">
        <div class="text-over">
            <p>Welcome!</p>
        </div>
     </div>
    <div class="contentBox">
        <div class="formBox">
            <h2>Login</h2>
            <?php if(isset($_GET['error'])){  ?>
            <div class="alert alert-danger" role="alert">
            <i class="bi bi-exclamation-circle"></i>
             <?php  $errorMessage = htmlspecialchars($_GET['error']);
             echo "$errorMessage!"; ?>
             </div>
             <?php } ?> 
    <form method="post" action="validate.php" class="form-admin">    
      <div class="mb-3">
        <label for="exampleInputEmail1" class="form-label">Email Required</label>
        <input type="email" class="form-control" id="exampleInputEmail1" aria-describedby="emailHelp" 
                      placeholder="Enter Email"
                      name="name">
        <div id="emailHelp" class="form-text">We'll never share your email with anyone else.</div>
      </div>
     <div class="mb-3">
       <label for="exampleInputPassword1" class="form-label">Password</label>
       <input type="password" class="form-control" id="exampleInputPassword1" name="pass">
     </div>
     <div class="mb-3">
        <span class="forgot-pass px-2"><a href="forgotpass.php" name="forgot-pass">Forgot Password</a></span>
     </div>
     <div class="mb-3">
       <label  class="form-label">Login As</label>
       <select class="form-control" name="role">
         <option value="1">Admin</option>
         <option value="2">Student</option>
         <option value="3">Parent</option>

       </select>
     </div>
         <button type="submit" class="btn btn-primary">Submit</button>
         <div class="log-back mt-3"> <a href="start.php">Back Home</a></div>
    </form>
    
     </div>
     
   </div>
 </section>
  <!--footer-->
  
  <!--footer-->
    <script src="scriptz.js"></script>
</body>
</html>