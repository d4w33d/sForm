# sForm

Send form by email using PHP and log records.

## Usage

## Configuration

Edit `sform.xml` to configure sForm.

## Form

Every HTML form with `action` pointing to `sform.php` will execute the data
verification, based on the configuration previously set.

Basically, the data is retrieved from POST fields, so you'll must set
the form `method` to `POST`.

If you need to retrieve some data from the URL QueryStrings (GET),
you'll need to set the `HttpMethod` to `GET` on the affected fields.

A form example is available in `form-example.php`.

## Manage fill errors and restore previous data

Optionnally, you can use some PHP methods described under,
to display errors when the setting `Redirections/AfterError` redirects
to your form.

### Display some text (like a CSS class) on field error

```html
<!--
Will put the CSS class "has-error" to the field DIV
if some_field is not correctly filled by the user
-->
<div class="form-group <?php sForm::err('some_field', 'has-error'); ?>">
```

### Display the value previously entered by the user (or an empty string)

```html
<!-- Will display the previously entered value (HTML character are escaped) -->
<input type="text" name="some_field" value="<?php sForm::val('some_field'); ?>">
```

### Check a box

```html
<input type="checkbox" name="some_field" <?php sForm::checked('some_field'); ?>>
```

```html
<input type="radio" name="some_field" value="foo" <?php sForm::checked('some_field', 'foo'); ?>>
<input type="radio" name="some_field" value="bar" <?php sForm::checked('some_field', 'bar'); ?>>
```

### Select the previously selected option

```html
<select name="some_field">
  <option value="foo" <?php sForm::selected('some_field', 'foo'); ?>>Foo</option>
  <option value="bar" <?php sForm::selected('some_field', 'bar'); ?>>Bar</option>
</select>
```
