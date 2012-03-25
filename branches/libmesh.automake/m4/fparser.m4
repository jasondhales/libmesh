dnl -------------------------------------------------------------
dnl fparser
dnl -------------------------------------------------------------
AC_DEFUN([CONFIGURE_FPARSER], 
[
  AC_ARG_ENABLE(fparser,
                AC_HELP_STRING([--enable-fparser],
                               [build with fparser, from Juha Nieminen, Joel Yliluoma]),
		[case "${enableval}" in
		  yes)  enablefparser=yes ;;
		   no)  enablefparser=no ;;
 		    *)  AC_MSG_ERROR(bad value ${enableval} for --enable-fparser) ;;
		 esac],
		 [enablefparser=no])
		 #[enablefparser=$enableoptional])



  dnl The FPARSER API is distributed with libmesh, so we don't have to guess
  dnl where it might be installed...
  if (test $enablefparser = yes); then
     AC_PROG_YACC
     FPARSER_INCLUDE="-I\$(top_srcdir)/contrib/fparser"
     #FPARSER_LIBRARY="\$(EXTERNAL_LIBDIR)/libfparser\$(libext)"
     AC_DEFINE(HAVE_FPARSER, 1, [Flag indicating whether the library will be compiled with FPARSER support])
     libmesh_contrib_INCLUDES="$FPARSER_INCLUDE $libmesh_contrib_INCLUDES"
     AC_MSG_RESULT(<<< Configuring library with fparser support >>>)
  else
     FPARSER_INCLUDE=""
     #FPARSER_LIBRARY=""
     enablefparser=no
  fi

  AC_SUBST(FPARSER_INCLUDE)
  #AC_SUBST(FPARSER_LIBRARY)	
  AC_SUBST(enablefparser)
		 		 
  AM_CONDITIONAL(LIBMESH_ENABLE_FPARSER, test x$enablefparser = xyes)
  AC_CONFIG_FILES([contrib/fparser/Makefile])
])
