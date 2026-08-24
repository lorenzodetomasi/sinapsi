<?php
function ws_conversion_constants() {
	// Constants for expressing human-readable data sizes in their respective number of bytes.
	// @since 0.0.1
	define( 'KB_IN_BYTES', 1024 );
	define( 'MB_IN_BYTES', 1024 * KB_IN_BYTES );
	define( 'GB_IN_BYTES', 1024 * MB_IN_BYTES );
	define( 'TB_IN_BYTES', 1024 * GB_IN_BYTES );

	// Constants for expressing human-readable intervals
	// in their respective number of seconds.
	// Please note that these values are approximate and are provided for convenience.
	// For example, MONTH_IN_SECONDS wrongly assumes every month has 30 days and
	// YEAR_IN_SECONDS does not take leap years into account.
	// If you need more accuracy please consider using the DateTime class (https://secure.php.net/manual/en/class.datetime.php).
	// @since 0.0.1
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'HOUR_IN_SECONDS',   60 * MINUTE_IN_SECONDS );
	define( 'DAY_IN_SECONDS',    24 * HOUR_IN_SECONDS   );
	define( 'WEEK_IN_SECONDS',    7 * DAY_IN_SECONDS    );
	define( 'MONTH_IN_SECONDS',  30 * DAY_IN_SECONDS    );
	define( 'YEAR_IN_SECONDS',  365 * DAY_IN_SECONDS    );
}
// Converts a shorthand byte value to an integer byte value.
// @since 1.0
// @link https://secure.php.net/manual/en/function.ini-get.php
// @link https://secure.php.net/manual/en/faq.using.php#faq.using.shorthandbytes
// @param string $value A (PHP ini) byte value, either shorthand or ordinary.
// @return int An integer byte value.
function ws_convert_hr_to_bytes( $value ) {
	$value = strtolower( trim( $value ) );
	$bytes = (int) $value;

	if ( false !== strpos( $value, 'g' ) ) {
		$bytes *= GB_IN_BYTES;
	} elseif ( false !== strpos( $value, 'm' ) ) {
		$bytes *= MB_IN_BYTES;
	} elseif ( false !== strpos( $value, 'k' ) ) {
		$bytes *= KB_IN_BYTES;
	}
	// Deal with large (float) values which run into the maximum integer size.
	return min( $bytes, PHP_INT_MAX );
}

// Currencies
function get_int_curr_symbol() {
	return localeconv()[int_curr_symbol];
}
function get_currency_symbol($locale){
	//https://stackoverflow.com/questions/13897516/get-currency-symbol-in-php
	//https://www.xe.com/symbols.php
	//http://php.net/manual/en/numberformatter.formatcurrency.php
	if(empty($locale)){
		$locale = ws_locale();
	}
	$int_curr_symbol = '';
	if(localeconv()[currency_symbol] == 'EUR'){
		return '€';
	} else {
		return localeconv()[currency_symbol];
	}
}
// Prices
function getOffer($price = null, $itemprop = "offers"){
	$price = money_format('%!n', (float)$price);
	return '<span itemprop="'.$itemprop.'" itemscope itemtype="http://schema.org/Offer"><abbr class="currency" title="'.get_int_curr_symbol().'">'.get_currency_symbol().'<meta itemprop="priceCurrency" content="'.get_int_curr_symbol().'" /></abbr> <span itemprop="price">'.$price.'</span><meta itemprop="valueAddedTaxIncluded" content="false" /></span>';
}
function getItemPrice($item){
	if($item->price){
		$price = $item->price;
	} else if($item->minutes){
		$price = covertMinutesToPrice($item->minutes);
	}
	return (float)$price;
}
function covertMinutesToPrice($minutes, $pricePerMinute = null){
	if(!$pricePerMinute){
		global $pricePerMinute;
	}
	$price = $minutes * $pricePerMinute;
	return $price;
}
function convertDecimalToPercentage($decimal){
	return round((float)$decimal * 100 ) . '%';
}
function getTotalInstallmentValue($installmentValue){
	global $stampDutyValue;
	if($installmentValue > 77.47){
		return $installmentValue + $stampDutyValue;
	} else {
		return $installmentValue;
	}
}
// DateTime
function get_datetime($unixtimestamp, $format = WS_DEFAULT_DATE_FORMAT, $locale = false){
	if(empty($locale)){
		$locale = ws_locale();
	}
	return $unixtimestamp;
/*	$datetime = new DateTime($unixtimestamp);
	$formatter = new IntlDateFormatter($locale, IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);
	$formatter->setPattern($format);
	return $formatter->format($datetime);*/
}
