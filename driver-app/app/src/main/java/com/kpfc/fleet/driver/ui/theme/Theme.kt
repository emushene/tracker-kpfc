package com.kpfc.fleet.driver.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

private val LightColorScheme = lightColorScheme(
    primary = KpfcNavyPrimary,
    onPrimary = KpfcSurface,
    primaryContainer = KpfcNavyLight,
    onPrimaryContainer = KpfcNavyDark,
    secondary = KpfcGreenSuccess,
    onSecondary = KpfcSurface,
    background = KpfcBackground,
    surface = KpfcSurface,
    onBackground = KpfcTextPrimary,
    onSurface = KpfcTextPrimary
)

private val DarkColorScheme = darkColorScheme(
    primary = KpfcNavyLight,
    onPrimary = KpfcNavyDark,
    background = KpfcNavyDark,
    surface = Color(0xFF1E293B),
    onBackground = KpfcSurface,
    onSurface = KpfcSurface
)

@Composable
fun KpfcDriverTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit
) {
    val colorScheme = if (darkTheme) DarkColorScheme else LightColorScheme

    MaterialTheme(
        colorScheme = colorScheme,
        content = content
    )
}
