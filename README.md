# XPM-LicenseClaim
Licence claim process for XenForo Product Manager

Some assembly required.

## on the exporting side:

- Export the contents of the following query, replacing (...) with the list of product ids that are being exported.
 - xenproduct_external_licence2 is a staging table name and SHOULD NOT be xenproduct_external_licence
```
 create table as xenproduct_external_licence2
 SELECT item.cart_id, item.product_id, item.item_id, license.license_alias, license.license_url, license.expiry_date, license.purchase_date, license.license_optional_extras, cart.cart_key, user.user_id, user.username, user.email
 FROM xenproduct_cart_item AS item
 INNER JOIN xenproduct_license AS license ON
     (item.item_id = license.item_id)
 INNER JOIN xenproduct_cart AS cart ON
     (item.cart_id = cart.cart_id)
 INNER JOIN xf_user AS user ON
     (cart.user_id = user.user_id)
 WHERE item.product_id IN(...)
 ```
- export the table;
  ie: ```mysqldump xenproduct_external_licence2```


## Importing side:
- Insert site record, note the label is end-user viewable;
 ```
 insert ignore into xenproduct_site_claimable (site_claimable_id, label) values (1, 'Example.com')
 ```
- Recreate each add-on.
 - set the site + external product id as required.
- copy data over with the correct site_claimable_id
 ```
 insert ignore xenproduct_external_licence (site_claimable_id, cart_id, product_id, item_id, license_alias, license_url, expiry_date, purchase_date, license_optional_extras, cart_key, user_id, username, email)
 select 1, cart_id, product_id, item_id, license_alias, license_url, expiry_date, purchase_date, license_optional_extras, cart_key, user_id, username, email
 from xenproduct_external_licence2;
 ```
- cleanup staging table:
 ```
 drop table xenproduct_external_licence2;
 ```
  