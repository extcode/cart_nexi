.. include:: ../../../Includes.txt

Main Configuration
==================

The plugin needs to know the merchant e-mail address.

::

   plugin.tx_cartnexi {
       sandbox = 1
       alias = ALIAS_WEB_12345678
       macHash = 12345678901234567890123456789012
   }

|

.. container:: table-row

   Property
         plugin.tx_cartnexi.sandbox
   Data type
         boolean
   Description
         This configuration determines whether the extension is in live or in sandbox mode.
   Default
         The default value is chosen so that the plugin is always in sandbox mode after installation, so that payment can be tested with Nexi.

.. container:: table-row

   Property
         plugin.tx_cartnexi.alias
   Data type
         boolean
   Description
         The alias for your account. You can find it in your account settings.

.. container:: table-row

   Property
         plugin.tx_cartnexi.macHash
   Data type
         boolean
   Description
         The hash for your account. You can find it in your account settings.
