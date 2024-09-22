/**
 * This file includes general methods for all maps.
 * */

/**
 * Return custom [lat,long] of iranian province
 * GENERAL METHOD
 * @return array
 * */
function pws_map_get_province_location(province) {
    switch (province) {
        case "استان آذربایجان شرقی":
        case "آذربایجان شرقی":
            return [46.29652594872965, 38.07334959970686];
        case "استان آذربایجان غربی":
        case "آذربایجان غربی":
            return [45.066199010210255, 37.549090717092994];
        case "استان اردبیل":
        case "اردبیل":
            return [48.29705810186877, 38.250977963284896];
        case "استان اصفهان":
        case "اصفهان":
            return [51.667879557967524, 32.64673138417923];
        case "استان البرز":
        case "البرز":
            return [51.007535158359644, 35.81196370276513];
        case "استان ایلام":
        case "ایلام":
            return [46.42491419928686, 33.636365996683466];
        case "استان بوشهر":
        case "بوشهر":
            return [50.86857312232024, 28.93175276389951];
        case "استان تهران":
        case "تهران":
            return [51.33774439566025, 35.6997006457524];
        case "استان چهارمحال و بختیاری":
        case "چهارمحال و بختیاری":
        case "استان چهار محال بختیاری":
            return [50.84949361049016, 32.32563021660313];
        case "استان خراسان جنوبی":
        case "خراسان جنوبی":
            return [59.21695375448638, 32.86310366749149];
        case "استان خراسان رضوی":
        case "خراسان رضوی":
            return [59.60612708710633, 36.29749367352903];
        case "استان خراسان شمالی":
        case "خراسان شمالی":
            return [57.33164422289255, 37.47623251667375];
        case "استان خوزستان":
        case "خوزستان":
            return [48.679357419175574, 31.323059172676807];
        case "استان زنجان":
        case "زنجان":
            return [48.48300964754603, 36.66799998523621];
        case "استان سمنان":
        case "سمنان":
            return [53.38874832538042, 35.58316228924757];
        case "استان سیستان و بلوچستان":
        case "سیستان و بلوچستان":
            return [60.86376908620963, 29.489146181976437];
        case "استان فارس":
        case "فارس":
            return [52.5385086580618, 29.603818535342484];
        case "استان قزوین":
        case "قزوین":
            return [50.00488463587101, 36.27970518245759];
        case "استان قم":
        case "قم":
            return [50.88227991502279, 34.64630675359567];
        case "استان کردستان":
        case "کردستان":
            return [47.002433312163674, 35.3114839356263];
        case "استان کرمان":
        case "کرمان":
            return [57.066016844077694, 30.292465037358852];
        case "استان کرمانشاه":
        case "کرمانشاه":
            return [47.07327816944752, 34.32392220244171];
        case "استان کهگیلویه و بویراحمد":
        case "کهگیلویه و بویراحمد":
            return [51.57938820663321, 30.667254302880878];
        case "استان گلستان":
        case "گلستان":
            return [54.43273154185354, 36.84175133875932];
        case "استان گیلان":
        case "گیلان":
            return [49.58475222497668, 37.27888580875177];
        case "استان لرستان":
        case "لرستان":
            return [48.35352126935422, 33.48489556493388];
        case "استان مازندران":
        case "مازندران":
            return [53.058534799566075, 36.56589812594005];
        case "استان مرکزی":
        case "مرکزی":
            return [49.690812344504934, 34.095494177321996];
        case "استان هرمزگان":
        case "هرمزگان":
            return [56.27686633792305, 27.179653213579527];
        case "استان همدان":
        case "همدان":
            return [48.51427475358636, 34.79871932655388];
        case "استان یزد":
        case "یزد":
            return [54.364528979651965, 31.888301558794836];
        default:
            return null;
    }
}

/**
 * Check if current viewing page is admin area or not!
 * @return bool
 * */
function pws_is_admin() {
    return pws_map_params.is_admin !== '';
}

/**
 * Check map placement which passed in params
 * @return bool
 * */
function pws_map_after_checkout() {
    return pws_map_params.checkout_placement === 'after_form';
}

/**
 * Method to check if shipping state has been enabled
 * @return bool
 * */
function pws_checkout_shipping_address_enabled() {
    return jQuery('#ship-to-different-address-checkbox').is(':checked');
}

/**
 * Check if admin can edit and set new point on the map
 * */
function pws_map_admin_editing_enabled() {
    if (!pws_is_admin()) {
        return true;
    }
    return jQuery('#pws-map-admin-edit').is(':checked');
}

/**
 * Check if a number is utm!
 * */
function pws_map_is_utm(latitude, longitude) {
    // Check if either coordinate is outside the typical longitude/latitude ranges
    if (longitude > 180 || longitude < -180 || latitude > 90 || latitude < -90) {
        return true;
    }

    // Heuristic check: UTM coordinates are much larger, often in the range of tens of thousands
    if (Math.abs(longitude) > 10000 || Math.abs(latitude) > 10000) {
        return true;
    }

    return false;
}
(function ($) {
    $(document).ready(function () {
        /**
         * Toggle show map in billing and shipping forms of woocommerce checkout
         * */
        $("#ship-to-different-address-checkbox").change(function () {
            if (this.checked) {
                $('.woocommerce-billing-fields').find('.pws-map__container').hide();
            } else {
                $('.woocommerce-billing-fields').find('.pws-map__container').show();
            }
        });
    })

})(jQuery);