<?php

include_once 'config.php';
include_once 'database.php';

$db = new Database($host, $db_name, $db_username, $db_password, $charset);

$sql =
    "SELECT p.product_id, p.model as part_num, p.price, p.ean, p.image, 
    pd.name as product_name, pd.meta_title, pd.meta_description, 
    m.name as manufacturer, 
    ccc.name as base_name,
    cd.name as parent_name,
    cdc.name as child_name
FROM oc_product p
LEFT JOIN oc_product_description pd     ON p.product_id = pd.product_id
LEFT JOIN oc_manufacturer m             ON p.manufacturer_id = m.manufacturer_id
LEFT JOIN oc_product_to_category ptc    ON p.product_id = ptc.product_id
LEFT JOIN oc_category c                 ON ptc.category_id = c.category_id
LEFT JOIN oc_category_description cd    ON c.parent_id = cd.category_id
LEFT JOIN oc_category_description cdc   ON ptc.category_id = cdc.category_id
LEFT JOIN oc_category cat               ON c.parent_id = cat.category_id
LEFT JOIN oc_category_description ccc   ON cat.parent_id = ccc.category_id
WHERE p.status = 1 AND
pd.language_id = 1 AND 
cd.language_id = 1 AND  
ccc.language_id = 1 AND
cdc.language_id = 1";

$results = $db->getData($sql);

function addProducts($results, $file, $db)
{
    $xml = new SimpleXMLElement('<Products/>');

    foreach ($results as $data) {
        $product_id = $data['product_id'];

        $sqlAttributes =
            "SELECT pa.descr as attr_descr, ad.name as attr_name 
            FROM oc_product_attribute pa
        LEFT JOIN oc_attribute_description ad ON pa.attribute_id = ad.attribute_id
        WHERE pa.product_id = $product_id AND pa.language_id = 1 AND ad.language_id = 1";

        $result_attributes = $db->getData($sqlAttributes);

        $product = $xml->addChild('Product');
        $product->addChild('Identifier', $data['product_id']);
        $product->addChild('Manufacturer', $data['manufacturer']);
        $product->addChild('ProductNumber', $data['part_num']);
        $product->addChild('Name', htmlspecialchars($data['product_name']));
        $product->addChild('Product_url', htmlspecialchars('https://' . $_SERVER['SERVER_NAME'] . '/index.php?route=product/product&product_id=' . $data['product_id']));
        $product->addChild('Price', $data['price']);
        $product->addChild('net_price', round((float)($data['price'] / 1.2), 2));
        $product->addChild('Image_url', htmlspecialchars(image($data['image'])));
        $product->addChild('Category', $data['base_name'] . " > " . $data['parent_name'] . " > " . $data['child_name']);
        $product->addChild('Description', htmlspecialchars($data['meta_description']));
        $product->addChild('Delivery_Time', 1);
        $product->addChild('Delivery_Cost', deliveryCost($data['price']));
        $product->addChild('EAN_code', $data['ean']);
        $attributes = $product->addChild('Attributes', '');

        foreach ($result_attributes as $attr) {
            $attribute = $attributes->addChild('Attribute');
            $attribute->addChild('Attribute_name', $attr['attr_name']);
            $attribute->addChild('Attribute_value', $attr['attr_descr']);
        }
    }

    $xml->asXML($file);

    if ($xml) {
        echo "Successfully generated file: $file";
    } else {
        throw new Error('Big problem...');
    }
}


function deliveryCost($price)
{
    if ($price >= 500) {
        $delivery_cost = 'Безплатна';
    } else {
        $delivery_cost = '6 лв.';
    }
    return $delivery_cost;
}


function image($input)
{
    if ($input) {
        $site = 'https://' . $_SERVER['SERVER_NAME'];
        $url = $site . '/image/' . $input;
        return $url;
    }
    return null;
}

addProducts($results, $file, $db);
