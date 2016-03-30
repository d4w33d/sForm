<?php require 'sform.php'; ?>
<!DOCTYPE html>
<html lang=en>

  <head>

    <meta charset="utf-8">

    <title>sForm</title>

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap-theme.min.css">

  </head>

  <body>

    <br>

    <div class="container">
      <div class="row">
        <div class="col-md-6 col-md-offset-3">

          <!-- ///////////////////////////////////////////////////////////////////////////////////////////////// -->
          <!-- BEGIN FORM -->

          <form action="sform.php" method="post">
            <div class="panel panel-default">
              <div class="panel-heading">
                <strong>Please fill the form then click the button</strong>
              </div>
              <div class="panel-body">

                <div class="form-group <?php sForm::err('firstname', 'has-error'); ?>">
                  <label>First name: <span class="text-danger">*</span></label>
                  <input type="text" name="firstname" class="form-control" value="<?php sForm::val('firstname'); ?>">
                </div>

                <div class="form-group <?php sForm::err('lastname', 'has-error'); ?>">
                  <label>Last name: <span class="text-danger">*</span></label>
                  <input type="text" name="lastname" class="form-control" value="<?php sForm::val('lastname'); ?>">
                </div>

                <div class="form-group <?php sForm::err('birthday', 'has-error'); ?>">
                  <label>Birthday:</label>
                  <input type="text" name="birthday" class="form-control" value="<?php sForm::val('birthday'); ?>" placeholder="YYYY-MM-DD">
                </div>

                <div class="form-group <?php sForm::err('price', 'has-error'); ?>">
                  <label>Price:</label>
                  <input type="text" name="price" class="form-control" value="<?php sForm::val('price'); ?>">
                  <p class="help-block">Between 10 and 999.99</p>
                </div>

                <div class="checkbox">
                  <label>
                    <input
                      type="checkbox"
                      name="optin"
                      value="1"
                      <?php sForm::checked('optin'); ?>
                      >
                    Register to newsletter
                  </label>
                </div>

              </div>
              <div class="panel-footer text-right">

                <button type="submit" class="btn btn-primary">
                  <span class="glyphicon glyphicon-ok" aria-hidden="true"></span>
                  Submit form
                </button>

              </div>
            </div>
          </form>

          <!-- END FORM -->
          <!-- ///////////////////////////////////////////////////////////////////////////////////////////////// -->

        </div>
      </div>
    </div>

  </body>

</html>
