package com.kpfc.fleet.driver.ui.map

import android.annotation.SuppressLint
import android.webkit.WebChromeClient
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import com.kpfc.fleet.driver.data.model.TripDto
import com.kpfc.fleet.driver.data.model.TripStopDto
import com.kpfc.fleet.driver.ui.theme.KpfcNavyPrimary
import org.json.JSONArray
import org.json.JSONObject

/**
 * Full-screen Leaflet map embedded in a WebView.
 *
 * - Loads `assets/map.html` which uses OpenStreetMap tiles via Leaflet 1.9
 * - Passes trip stops as JSON to the JS `loadStops()` function
 * - Marks the first non-completed stop as "active" (centered on map)
 * - Requires INTERNET permission (already in manifest)
 */
@OptIn(ExperimentalMaterial3Api::class)
@SuppressLint("SetJavaScriptEnabled")
@Composable
fun LeafletMapScreen(
    trip: TripDto,
    onBack: () -> Unit
) {
    val context = LocalContext.current

    // Build the JSON payload for JS once, derived from trip stops
    val stopsJson = remember(trip.id, trip.stops) {
        buildStopsJson(trip.stops)
    }

    // Keep a stable WebView reference so we can call evaluateJavascript after page loads
    val webView = remember {
        WebView(context).apply {
            settings.apply {
                javaScriptEnabled = true
                domStorageEnabled = true
                loadWithOverviewMode = true
                useWideViewPort = true
                builtInZoomControls = false
                displayZoomControls = false
                cacheMode = WebSettings.LOAD_DEFAULT
            }
            webChromeClient = WebChromeClient()
            webViewClient = object : WebViewClient() {
                override fun onPageFinished(view: WebView?, url: String?) {
                    // Inject stop data once the HTML page is ready
                    view?.evaluateJavascript(
                        "loadStops(${stopsJson.replace("'", "\\'")});",
                        null
                    )
                }
            }
        }
    }

    // Reload stops if they change (e.g. after a refresh)
    LaunchedEffect(stopsJson) {
        webView.evaluateJavascript("if(typeof loadStops==='function'){loadStops($stopsJson);}", null)
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    androidx.compose.foundation.layout.Column {
                        Text(
                            text = "Trip Map",
                            fontSize = 17.sp,
                            fontWeight = FontWeight.Bold,
                            color = Color.White
                        )
                        Text(
                            text = trip.tripNumber,
                            fontSize = 12.sp,
                            color = Color(0xFFCBD5E1)
                        )
                    }
                },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(
                            imageVector = Icons.Default.ArrowBack,
                            contentDescription = "Back",
                            tint = Color.White
                        )
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = KpfcNavyPrimary)
            )
        }
    ) { innerPadding ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
        ) {
            AndroidView(
                factory = { webView },
                modifier = Modifier.fillMaxSize(),
                update = { view ->
                    // Load the page only once; updates go via evaluateJavascript
                    if (view.url == null) {
                        view.loadUrl("file:///android_asset/map.html")
                    }
                }
            )
        }
    }
}

/** Converts trip stops into the JSON format expected by the Leaflet HTML. */
private fun buildStopsJson(stops: List<TripStopDto>): String {
    // First non-completed stop is the "active" one
    val activeStopId = stops.firstOrNull {
        it.status != "completed" && it.status != "arrived"
    }?.id

    val array = JSONArray()
    stops.forEach { stop ->
        if (stop.latitude == null || stop.longitude == null) return@forEach
        val obj = JSONObject().apply {
            put("id", stop.id)
            put("sequence", stop.sequence)
            put("name", stop.locationName)
            put("address", stop.address ?: "")
            put("lat", stop.latitude)
            put("lng", stop.longitude)
            put("status", stop.status)
            put("isActive", stop.id == activeStopId)
        }
        array.put(obj)
    }
    return array.toString()
}
